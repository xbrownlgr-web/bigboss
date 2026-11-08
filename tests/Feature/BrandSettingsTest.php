<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Support\BrandPalettes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_settings_expose_the_default_branding(): void
    {
        $response = $this->getJson('/api/v1/settings/public')->assertSuccessful();

        $response->assertJsonPath('branding.app_name', 'BIG BOSS');
        $response->assertJsonPath('branding.palette.key', 'green_black');
        $response->assertJsonPath('branding.palette.primary', '#0C2A1E');
        $response->assertJsonPath('branding.palette.accent', '#22C55E');
    }

    public function test_nothing_from_binho_flash_is_ever_sent_to_the_big_boss_app(): void
    {
        // Séparation stricte exigée par le fondateur (incident du 2026-11-08 : liens de Binho
        // affichés dans l'app BIG BOSS). Champs vides = réseau non affiché, jamais Binho.
        foreach (['/api/v1/settings/public', '/api/v1/app/version'] as $url) {
            $content = $this->getJson($url)->assertSuccessful()->getContent();
            $this->assertStringNotContainsStringIgnoringCase('binho', $content, $url);
        }

        $links = $this->getJson('/api/v1/settings/public')->json('community_links');
        $this->assertSame('', $links['facebook']);
        $this->assertSame('', $links['instagram']);
    }

    public function test_the_chosen_name_and_palette_are_served_to_the_app(): void
    {
        AppSetting::setValue('brand_app_name', 'BIG BOSS');
        AppSetting::setValue('brand_palette', 'emerald_gold');

        $response = $this->getJson('/api/v1/settings/public')->assertSuccessful();

        $response->assertJsonPath('branding.app_name', 'BIG BOSS');
        $response->assertJsonPath('branding.palette.key', 'emerald_gold');
        $response->assertJsonPath('branding.palette.primary', BrandPalettes::PALETTES['emerald_gold']['primary']);
    }

    public function test_an_unknown_palette_falls_back_to_the_default(): void
    {
        AppSetting::setValue('brand_palette', 'does_not_exist');

        $this->getJson('/api/v1/settings/public')
            ->assertSuccessful()
            ->assertJsonPath('branding.palette.key', 'green_black');
    }
}
