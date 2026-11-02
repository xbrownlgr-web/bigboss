<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Alertes WhatsApp à l'admin via CallMeBot (https://www.callmebot.com).
 */
class WhatsAppNotifier
{
    private const ENDPOINT = 'https://api.callmebot.com/whatsapp.php';

    /**
     * « +229 01 97 00 00 00 », « 00229… » ou « 229… » → « +2290197000000 ».
     */
    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return '+' . $digits;
    }

    public static function configured(): bool
    {
        return self::phone() !== null && (bool) self::apiKey();
    }

    /**
     * @return string|null null si CallMeBot a accepté le message, sinon la raison de l'échec.
     */
    public function send(string $text, array $context = []): ?string
    {
        if (! self::configured()) {
            Log::info('Alerte WhatsApp ignorée : numéro ou clé CallMeBot manquant.', $context);

            return 'Numéro WhatsApp ou clé CallMeBot manquant.';
        }

        try {
            // Paramètres passés en tableau : le « + » du numéro est correctement encodé (%2B).
            $response = Http::timeout(15)->get(self::ENDPOINT, [
                'phone' => self::phone(),
                'text' => $text,
                'apikey' => self::apiKey(),
            ]);

            // CallMeBot répond 200 même en cas d'erreur : on lit le texte de la réponse.
            $body = trim(preg_replace('/\s+/', ' ', strip_tags($response->body())));

            if ($response->successful() && preg_match('/queued|sent|envoy/i', $body)) {
                return null;
            }

            $reason = $body !== '' ? mb_strimwidth($body, 0, 240, '…') : 'erreur HTTP ' . $response->status();
            Log::warning('Alerte WhatsApp refusée par CallMeBot.', [...$context, 'reason' => $reason]);

            return $reason;
        } catch (Throwable $exception) {
            Log::warning('CallMeBot injoignable.', [...$context, 'error' => $exception->getMessage()]);

            return 'CallMeBot injoignable : ' . $exception->getMessage();
        }
    }

    private static function phone(): ?string
    {
        return self::normalizePhone(AppSetting::value('callmebot_phone', env('CALLMEBOT_PHONE')));
    }

    private static function apiKey(): ?string
    {
        return AppSetting::value('callmebot_apikey', env('CALLMEBOT_APIKEY')) ?: null;
    }
}
