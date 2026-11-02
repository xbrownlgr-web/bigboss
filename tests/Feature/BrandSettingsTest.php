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
        $response->assertJsonPath('branding.palette.key', 'navy_gold');
        $response->assertJsonPath('branding.palette.primary', '#1A2E5A');
        $response->assertJsonPath('branding.palette.accent', '#FFD700');
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
            ->assertJsonPath('branding.palette.key', 'navy_gold');
    }
}
