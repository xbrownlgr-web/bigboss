<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Pronostic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VipOfferTest extends TestCase
{
    use RefreshDatabase;

    private function pronostic(string $niveau): Pronostic
    {
        return Pronostic::create([
            'niveau' => $niveau,
            'couleur' => '#FFD700',
            'cote_totale' => 6.4,
            'probabilite' => 31,
            'evenements' => [
                ['match' => 'Arsenal vs Chelsea', 'pronostic' => 'Victoire Arsenal', 'cote' => 1.7],
                ['match' => 'PSG vs Lyon', 'pronostic' => 'Victoire PSG', 'cote' => 1.5],
            ],
            'code_1xbet' => 'ABC123',
            'match_date' => now()->toDateString(),
            'match_time' => '00:00',
            'team_home' => 'Arsenal',
            'team_away' => 'Chelsea',
            'competition' => 'Combiné ' . $niveau,
            'prediction' => 'Combiné 2 événements',
            'odds' => 6.4,
            'confidence' => 31,
            'analysis' => 'Analyse secrète.',
            'is_vip' => true,
            'published_at' => now(),
        ]);
    }

    public function test_vip_coupons_never_reveal_their_content_to_the_app(): void
    {
        $this->pronostic('Gold');
        // Un seul niveau gratuit désormais : Starter. Bronze a rejoint les niveaux VIP.
        $this->pronostic('Starter');

        $response = $this->getJson('/api/v1/pronostics')->assertOk();

        $gold = collect($response->json('pronostics'))->firstWhere('niveau', 'Gold');
        $this->assertTrue($gold['locked']);
        $this->assertSame([], $gold['evenements']);
        $this->assertSame(2, $gold['evenements_count']);
        $this->assertSame('', $gold['analysis']);
        $this->assertNull($gold['code_1xbet']);
        $this->assertSame('6.40', $gold['cote_totale']);
        $this->assertStringNotContainsString('Arsenal', json_encode($gold));
        $this->assertStringNotContainsString('secrète', json_encode($gold));

        $starter = collect($response->json('pronostics'))->firstWhere('niveau', 'Starter');
        $this->assertFalse($starter['locked']);
        $this->assertCount(2, $starter['evenements']);
    }

    public function test_the_app_receives_prices_details_and_whatsapp_number(): void
    {
        AppSetting::setValue('vip_whatsapp_number', '+229 01 97 00 00 00');
        AppSetting::setValue('vip_gold_price', '5 000 FCFA');
        AppSetting::setValue('vip_gold_details', 'Accès 30 jours au coupon Gold.');

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('vip.whatsapp', '2290197000000')
            ->assertJsonPath('vip.offers.Gold.price', 5000)
            ->assertJsonPath('vip.offers.Gold.details', 'Accès 30 jours au coupon Gold.')
            ->assertJsonPath('vip.offers.Silver.price', null);
    }

    public function test_the_admin_sets_vip_prices_in_settings(): void
    {
        $admin = User::factory()->create(['email' => 'admin@binhoflash.com', 'is_active' => true]);

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Coupons VIP (Bronze, Silver, Gold, Platinum)')
            ->assertSee('Prix du VIP Platinum (FCFA)');

        $this->actingAs($admin)
            ->post('/admin/settings', [
                'vip_whatsapp_number' => '+229 01 97 00 00 00',
                'vip_platinum_price' => '10000',
                'vip_platinum_details' => "Coupon Platinum du jour\nEnvoyé sur WhatsApp",
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('10000', AppSetting::value('vip_platinum_price'));
        $this->assertSame("Coupon Platinum du jour\nEnvoyé sur WhatsApp", AppSetting::value('vip_platinum_details'));
    }
}
