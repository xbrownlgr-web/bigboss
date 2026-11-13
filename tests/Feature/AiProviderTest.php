<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Support\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DeepSeek remplace OpenRouter dès que sa clé est renseignée (plus de crédit OpenRouter, 2026-11-13).
 */
class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_deepseek_takes_over_as_soon_as_its_key_is_set(): void
    {
        AppSetting::setValue('openrouter_api_key', 'sk-or-test');
        $this->assertFalse(AiProvider::isDeepSeek());
        $this->assertSame(AiProvider::OPENROUTER_ENDPOINT, AiProvider::endpoint());
        $this->assertSame('sk-or-test', AiProvider::key());
        $this->assertSame('anthropic/claude-sonnet-5.5', AiProvider::model('anthropic/claude-sonnet-5.5'));

        AppSetting::setValue('deepseek_api_key', 'sk-ds-test');
        $this->assertTrue(AiProvider::isDeepSeek());
        $this->assertSame('https://api.deepseek.com/chat/completions', AiProvider::endpoint());
        $this->assertSame('sk-ds-test', AiProvider::key());
        // Les noms de modèles OpenRouter ne veulent rien dire chez DeepSeek.
        $this->assertSame('deepseek-chat', AiProvider::model('anthropic/claude-sonnet-5.5'));

        // Retour à OpenRouter possible d'un réglage.
        AppSetting::setValue('ai_provider', 'openrouter');
        $this->assertSame('sk-or-test', AiProvider::key());
    }
}
