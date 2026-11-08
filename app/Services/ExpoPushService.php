<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExpoPushService
{
    private const ENDPOINT = 'https://exp.host/--/api/v2/push/send';

    // Expo accepte jusqu'à 100 messages par requête.
    private const BATCH_SIZE = 100;

    public function sendToActiveUsers(string $title, string $body, array $data = []): int
    {
        return $this->sendToUsers(User::query(), $title, $body, $data);
    }

    /**
     * Envoie à une sélection d'utilisateurs (ex. abonnés VIP Gold) ; seuls les comptes actifs
     * avec un appareil enregistré sont visés.
     *
     * @param Builder<User> $users
     */
    public function sendToUsers(Builder $users, string $title, string $body, array $data = []): int
    {
        $sent = 0;

        $users
            ->where('is_active', true)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', 'like', 'ExponentPushToken[%')
            ->select(['id', 'fcm_token'])
            ->chunkById(self::BATCH_SIZE, function ($users) use ($title, $body, $data, &$sent) {
                $messages = $users
                    ->map(fn (User $user) => $this->message($user->fcm_token, $title, $body, $data))
                    ->values()
                    ->all();

                if ($this->post($messages, ['user_ids' => $users->pluck('id')->all()])) {
                    $sent += count($messages);
                }
            });

        return $sent;
    }

    /**
     * @return string|null null si Expo a accepté la notification, sinon la raison de l'échec.
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): ?string
    {
        $token = $user->fcm_token;

        if (! $token || ! str_starts_with($token, 'ExponentPushToken[')) {
            return 'Aucun appareil enregistré pour cet utilisateur.';
        }

        $errors = [];
        $sent = $this->post([$this->message($token, $title, $body, $data)], ['user_id' => $user->id], $errors);

        return $sent && $errors === [] ? null : ($errors[0] ?? 'Le service Expo est injoignable.');
    }

    private function message(string $token, string $title, string $body, array $data): array
    {
        return [
            'to' => $token,
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'priority' => 'high',
            // Identifiant TECHNIQUE du canal Android créé par l'app BIG BOSS (affiché « Alertes BIG BOSS »
            // sur le téléphone). Hérité du fork : NE PAS le renommer, les APK déjà installés ne
            // connaissent que celui-ci (sinon notifications sans son ni bannière).
            'channelId' => 'binho_alerts',
            'interruptionLevel' => 'active',
            'data' => $data,
        ];
    }

    /**
     * @param array<int, string> $errors raisons des refus renvoyées par Expo
     */
    private function post(array $messages, array $context, array &$errors = []): bool
    {
        try {
            $response = Http::timeout(10)
                ->acceptJson()
                ->post(self::ENDPOINT, $messages);

            if (! $response->successful()) {
                Log::warning('Notification Expo non envoyée.', [
                    ...$context,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $tickets = $response->json('data') ?? [];
            $this->forgetInvalidTokens($messages, $tickets);

            foreach ($tickets as $ticket) {
                if (($ticket['status'] ?? null) === 'error') {
                    $errors[] = trim(($ticket['details']['error'] ?? 'Erreur') . ' : ' . ($ticket['message'] ?? ''));
                }
            }

            if ($errors !== []) {
                Log::warning('Notifications Expo refusées.', [...$context, 'errors' => array_slice(array_unique($errors), 0, 5)]);
            }

            return true;
        } catch (Throwable $exception) {
            Log::warning('Erreur lors de l’envoi de la notification Expo.', [
                ...$context,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Efface les tokens des téléphones où l'app a été désinstallée, pour ne plus les solliciter.
     */
    private function forgetInvalidTokens(array $messages, array $tickets): void
    {
        foreach ($tickets as $index => $ticket) {
            if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($messages[$index])) {
                User::query()
                    ->where('fcm_token', $messages[$index]['to'])
                    ->update(['fcm_token' => null]);
            }
        }
    }
}
