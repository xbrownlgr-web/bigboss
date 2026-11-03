<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\DepositNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_public_page_follows_the_admin_settings(): void
    {
        Http::fake(['*' => Http::response('', 404)]);
        DepositNumber::create(['country' => 'benin', 'operator' => 'MTN MoMo', 'number' => '01 97 00 00 00', 'account_name' => 'JEAN DUPONT']);
        DepositNumber::create(['country' => 'cameroun', 'operator' => 'Orange Money', 'number' => '6 99 00 00 00', 'account_name' => 'PAUL']);
        AppSetting::setValue('vip_silver_price', '10000');
        AppSetting::setValue('vip_gold_price', '');
        AppSetting::setValue('telegram_url', 'https://t.me/binho_test');

        $this->get('/')
            ->assertOk()
            ->assertSee('Votre caisse 1xBet &amp; Melbet', false)
            ->assertSee('10 000 F')
            ->assertSee('Sur demande')
            ->assertSee('MTN MoMo')
            ->assertSee('Orange Money</span>', false)
            ->assertDontSee('Celtiis</span>', false)
            ->assertSee('https://t.me/binho_test')
            // Aperçu des liens partagés : image et adresse de BIG BOSS (plus jamais binhoflash.com).
            ->assertSee('/images/site/partage-bigboss.jpg')
            ->assertDontSee('binhoflash.com')
            // Jamais de numéro de dépôt sur la page publique : il ne s'affiche que dans l'application.
            ->assertDontSee('01 97 00 00 00')
            ->assertDontSee('6 99 00 00 00');
    }
}
