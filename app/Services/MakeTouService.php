<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Paiement des abonnements VIP via MAKETOU (api.maketou.net, page de paiement Moneroo).
 * Un seul produit « prix libre » sert pour tous les niveaux : on envoie le montant exact
 * (prix du VIP + frais %), ce qui permet d'ajouter la commission au moment du paiement.
 * Clé API, identifiant du produit et pourcentage de frais sont réglés dans Admin > Paiement.
 */
class MakeTouService
{
    private const BASE_URL = 'https://api.maketou.net';

    /**
     * Crée le paiement d'un niveau VIP pour un utilisateur.
     *
     * @param 'week'|'month' $duration abonnement à la semaine (essai moins engageant) ou au mois
     * @return array{cart_id: string, redirect_url: string, amount: int}
     */
    public function checkout(User $user, string $level, string $duration = 'month'): array
    {
        $apiKey = trim((string) AppSetting::value('maketou_api_key'));
        $productId = trim((string) AppSetting::value('maketou_product_id'));

        if ($apiKey === '' || $productId === '') {
            throw new RuntimeException('Paiement indisponible : MAKETOU n’est pas encore configuré (Admin > Paiement).');
        }

        $offer = AppSetting::vipOffers()[$level] ?? [];
        $price = $duration === 'week' ? ($offer['price_week'] ?? null) : ($offer['price'] ?? null);

        if (! $price || $price <= 0) {
            $label = $duration === 'week' ? 'à la semaine' : 'au mois';
            throw new RuntimeException('Le prix de ' . AppSetting::offerName($level) . ' ' . $label . ' n’est pas défini.');
        }

        $fee = (float) (AppSetting::value('maketou_fee_percent') ?? 7);
        $amount = (int) round($price * (1 + $fee / 100));
        [$first, $last] = $this->splitName($user->name);
        $base = rtrim((string) config('app.url'), '/');

        $response = Http::asJson()
            ->acceptJson()
            ->withToken($apiKey)
            ->timeout(30)
            ->post(self::BASE_URL . '/api/v1/stores/cart/checkout', [
                'productDocumentId' => $productId,
                'email' => $user->email,
                'firstName' => $first,
                'lastName' => $last,
                // Numéro au format international (« +229… ») : les espaces et tirets saisis par le
                // client faisaient refuser la demande (HTTP 422). Omis s'il est vide ou trop court.
                ...($this->phone($user->phone) ? ['phone' => $this->phone($user->phone)] : []),
                'customerPrice' => $amount,
                'redirectURL' => $base . '/vip/paiement/retour',
                'webhookUrl' => $base . '/api/v1/vip/maketou/webhook',
                'meta' => ['user_id' => $user->id, 'level' => $level, 'duration' => $duration],
            ]);

        if (! $response->successful()) {
            // La raison exacte (champ refusé…) est gardée dans les journaux pour pouvoir corriger.
            Log::warning('MAKETOU a refusé un paiement.', [
                'status' => $response->status(),
                'body' => mb_strimwidth($response->body(), 0, 1500, '…'),
                'user_id' => $user->id,
                'level' => $level,
            ]);
            $reason = (string) ($response->json('message') ?? $response->json('error.message') ?? '');

            throw new RuntimeException('Le paiement en ligne n’a pas pu démarrer'
                . ($reason !== '' ? ' (' . mb_strimwidth($reason, 0, 160, '…') . ')' : ' (HTTP ' . $response->status() . ')')
                . '. Réessayez, ou utilisez « Payer autrement (WhatsApp) ».');
        }

        $cartId = (string) ($response->json('cart.id') ?? '');
        $redirectUrl = (string) ($response->json('redirectUrl') ?? '');

        if ($cartId === '' || $redirectUrl === '') {
            throw new RuntimeException('Réponse MAKETOU incomplète : paiement impossible.');
        }

        return ['cart_id' => $cartId, 'redirect_url' => $redirectUrl, 'amount' => $amount];
    }

    /**
     * Statut d'un panier. « completed » = payé. Null si indisponible.
     */
    public function status(string $cartId): ?string
    {
        $apiKey = trim((string) AppSetting::value('maketou_api_key'));

        if ($apiKey === '' || $cartId === '') {
            return null;
        }

        $response = Http::acceptJson()
            ->withToken($apiKey)
            ->timeout(30)
            ->get(self::BASE_URL . '/api/v1/stores/cart/' . $cartId);

        if (! $response->successful()) {
            return null;
        }

        return (string) ($response->json('cart.status') ?? $response->json('status') ?? '') ?: null;
    }

    /**
     * @return array{0: string, 1: string} prénom, nom
     */
    /**
     * « 01 97 45 12 33 », « +229 0197… » → « +2290197451233 » ; null si inutilisable.
     */
    private function phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) < 8) {
            return null;
        }

        // Numéro local béninois (10 chiffres commençant par 01) : indicatif ajouté.
        if (strlen($digits) === 10 && str_starts_with($digits, '01')) {
            $digits = '229' . $digits;
        }

        return '+' . $digits;
    }

    private function splitName(?string $name): array
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim((string) $name)) ?: []));
        $first = $parts[0] ?? 'Client';
        $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : $first;

        return [$first, $last];
    }
}
