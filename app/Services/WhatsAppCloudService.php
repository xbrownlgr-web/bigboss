<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Alerte WhatsApp admin via l'API Cloud officielle de Meta (template « Utilitaire ») — utilisée
 * uniquement pour les nouvelles recharges/retraits (TransactionController). Les autres alertes
 * (pronostics, messages support, bouton de test générique) restent sur WhatsAppNotifier/CallMeBot,
 * volontairement non touché.
 *
 * Config entièrement par variables d'environnement (voir config/services.php) :
 * WHATSAPP_API_VERSION, WHATSAPP_PHONE_NUMBER_ID, WHATSAPP_ACCESS_TOKEN, WHATSAPP_TEMPLATE_NAME,
 * ADMIN_WHATSAPP_NUMBER. Le nombre de variables du template n'est pas figé dans le code : send()
 * prend un tableau ordonné, un futur changement de modèle n'impose pas de réécrire cette classe.
 */
class WhatsAppCloudService
{
    private const MAX_ATTEMPTS = 3; // 1 essai + 2 nouvelles tentatives, comme demandé.

    private const RETRY_DELAY_MS = [1000, 2000];

    public static function configured(): bool
    {
        $config = config('services.whatsapp_cloud');

        return ! empty($config['phone_number_id'])
            && ! empty($config['token'])
            && ! empty($config['template'])
            && ! empty($config['admin_number']);
    }

    /**
     * Alerte « nouvelle recharge/retrait » : {{1}} type, {{2}} montant, {{3}} opérateur, {{4}} client.
     */
    public function sendTransactionAlert(string $type, string $amount, string $operator, string $client, array $context = []): ?string
    {
        return $this->send([$type, $amount, $operator, $client], $context);
    }

    /**
     * @param array<int, string> $parameters variables du template, dans l'ordre ({{1}}, {{2}}, …)
     * @return string|null null si envoyé, sinon la raison de l'échec
     */
    public function send(array $parameters, array $context = []): ?string
    {
        if (! self::configured()) {
            Log::info('Alerte WhatsApp Cloud ignorée : configuration incomplète.', $context);

            return 'Configuration WhatsApp Cloud incomplète (variables d’environnement manquantes).';
        }

        $config = config('services.whatsapp_cloud');
        $url = "https://graph.facebook.com/{$config['version']}/{$config['phone_number_id']}/messages";

        $body = [
            'messaging_product' => 'whatsapp',
            'to' => $config['admin_number'],
            'type' => 'template',
            'template' => [
                'name' => $config['template'],
                'language' => ['code' => 'fr'],
                'components' => [[
                    'type' => 'body',
                    'parameters' => array_map(fn ($value) => ['type' => 'text', 'text' => (string) $value], $parameters),
                ]],
            ],
        ];

        $lastReason = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                $response = Http::timeout(15)
                    ->withToken($config['token'])
                    ->post($url, $body);
            } catch (Throwable $exception) {
                // Jamais le jeton dans les logs : $exception->getMessage() ne le contient pas
                // (il vient du header, pas de l'URL ni du corps de la requête).
                $lastReason = 'Réseau WhatsApp Cloud injoignable : ' . $exception->getMessage();
                Log::warning('WhatsApp Cloud injoignable.', [...$context, 'attempt' => $attempt, 'error' => $exception->getMessage()]);

                if ($attempt < self::MAX_ATTEMPTS) {
                    usleep(self::RETRY_DELAY_MS[$attempt - 1] * 1000);
                }

                continue;
            }

            if ($response->successful()) {
                return null;
            }

            $lastReason = 'WhatsApp Cloud a refusé le message (HTTP ' . $response->status() . ') : '
                . mb_strimwidth((string) ($response->json('error.message') ?? $response->body()), 0, 240, '…');

            // 5xx (panne côté Meta) : on retente. 4xx (jeton invalide, modèle inconnu, numéro
            // refusé…) : retenter ne changera rien, inutile d'insister.
            if (! $response->serverError()) {
                Log::warning('WhatsApp Cloud a refusé la demande (erreur non temporaire).', [...$context, 'status' => $response->status(), 'reason' => $lastReason]);

                return $lastReason;
            }

            Log::warning('WhatsApp Cloud a refusé la demande (erreur serveur, nouvelle tentative).', [...$context, 'attempt' => $attempt, 'status' => $response->status()]);

            if ($attempt < self::MAX_ATTEMPTS) {
                usleep(self::RETRY_DELAY_MS[$attempt - 1] * 1000);
            }
        }

        return $lastReason;
    }
}
