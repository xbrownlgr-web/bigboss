<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Fournisseur de l'IA (pronostics, scores exacts, assistant) : DeepSeek ou OpenRouter.
 * Les deux parlent le même format (« chat/completions », réponse JSON), seuls l'adresse, la clé et
 * le nom du modèle changent. Réglage « ai_provider » (Admin > Paramètres > IA) :
 * auto (défaut) = DeepSeek dès que sa clé est renseignée, sinon OpenRouter ; ou deepseek / openrouter.
 * Ajouté le 2026-11-13 : plus de crédit OpenRouter, crédit disponible chez DeepSeek.
 */
class AiProvider
{
    public const DEEPSEEK_ENDPOINT = 'https://api.deepseek.com/chat/completions';

    public const OPENROUTER_ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    public const DEEPSEEK_DEFAULT_MODEL = 'deepseek-chat';

    public static function isDeepSeek(): bool
    {
        $choice = strtolower(trim((string) AppSetting::value('ai_provider', 'auto')));

        return match ($choice) {
            'deepseek' => true,
            'openrouter' => false,
            default => trim((string) AppSetting::value('deepseek_api_key')) !== '',
        };
    }

    public static function name(): string
    {
        return self::isDeepSeek() ? 'DeepSeek' : 'OpenRouter';
    }

    public static function endpoint(): string
    {
        return self::isDeepSeek() ? self::DEEPSEEK_ENDPOINT : self::OPENROUTER_ENDPOINT;
    }

    public static function key(): ?string
    {
        $key = trim((string) AppSetting::value(self::isDeepSeek() ? 'deepseek_api_key' : 'openrouter_api_key'));

        return $key !== '' ? $key : null;
    }

    /**
     * Modèle à utiliser. Avec DeepSeek, les noms OpenRouter (« anthropic/… ») ne veulent rien dire :
     * on prend le réglage « deepseek_model » (défaut deepseek-chat).
     */
    public static function model(?string $openRouterModel): string
    {
        if (self::isDeepSeek()) {
            return trim((string) AppSetting::value('deepseek_model')) ?: self::DEEPSEEK_DEFAULT_MODEL;
        }

        return (string) $openRouterModel;
    }
}
