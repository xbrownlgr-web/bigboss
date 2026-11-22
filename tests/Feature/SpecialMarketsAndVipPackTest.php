<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Pronostic;
use App\Models\User;
use App\Models\VipSubscription;
use App\Services\SpecialMarketsGenerator;
use App\Services\VipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SpecialMarketsAndVipPackTest extends TestCase
{
    use RefreshDatabase;

    private function sampleSpecialPronostic(string $niveau, array $event, float $odds = 2.05): Pronostic
    {
        return Pronostic::create([
            'niveau' => $niveau,
            'couleur' => '#D97706',
            'cote_totale' => $odds,
            'probabilite' => 54,
            'evenements' => [$event],
            'code_1xbet' => 'SECRET99',
            'match_date' => now()->toDateString(),
            'match_time' => '18:00',
            'team_home' => 'Arsenal',
            'team_away' => 'Chelsea',
            'competition' => 'Premier League',
            'prediction' => $event['pronostic'] ?? 'Test',
            'odds' => $odds,
            'confidence' => 54,
            'analysis' => 'Analyse confidentielle sur Saka et arbitre Taylor.',
            'is_vip' => true,
            'published_at' => now(),
        ]);
    }

    public function test_public_settings_expose_all_five_categories_and_pack_complet_without_binho(): void
    {
        $response = $this->getJson('/api/v1/settings/public')->assertOk();

        $response->assertJsonPath('vip.offers.GrossesCotes.price', 5000);
        $response->assertJsonPath('vip.offers.Cartons.price', 3500);
        $response->assertJsonPath('vip.offers.Buteurs.price', 3500);
        $response->assertJsonPath('vip.offers.Complet.price', 25000);
        $response->assertJsonPath('pronostic_schedule.GrossesCotes', ['10:30', '15:30']);
        $response->assertJsonPath('pronostic_schedule.Cartons', ['10:30', '15:30']);
        $response->assertJsonPath('pronostic_schedule.Buteurs', ['10:30', '15:30']);
        $this->assertStringNotContainsStringIgnoringCase('binho', $response->getContent());
    }

    public function test_separate_subscriptions_only_unlock_their_own_category_and_hide_secrets(): void
    {
        $grosse = $this->sampleSpecialPronostic('GrossesCotes', [
            'match' => 'Arsenal vs Chelsea',
            'pronostic' => 'Double chance 1X',
            'cote' => 1.50,
            'tier' => 'equilibre',
            'tier_label' => 'Équilibré',
            'risque' => 'eleve',
            'probabilite_globale' => 3.2,
        ], 30.45);

        $carton = $this->sampleSpecialPronostic('Cartons', [
            'match' => 'PSG vs OM',
            'pronostic' => 'Plus de 4,5 cartons jaunes',
            'cote' => 2.05,
            'confiance' => 'moyenne',
            'valeur' => 0.12,
        ], 2.05);

        $buteur = $this->sampleSpecialPronostic('Buteurs', [
            'match' => 'Real Madrid vs Barcelone',
            'joueur' => 'Kylian Mbappé',
            'marche' => 'Buteur à tout moment',
            'pronostic' => 'Kylian Mbappé — Buteur à tout moment',
            'cote' => 2.15,
            'confiance' => 'moyenne',
            'composition_confirmee' => false,
        ], 2.15);

        $user = User::factory()->create(['is_active' => true]);
        VipSubscription::create([
            'user_id' => $user->id,
            'level' => 'Cartons',
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        Sanctum::actingAs($user);
        $items = collect($this->getJson('/api/v1/pronostics')->assertOk()->json('pronostics'))->keyBy('id');

        // Cartons débloqué
        $this->assertFalse($items[$carton->id]['locked']);
        $this->assertCount(1, $items[$carton->id]['evenements']);

        // Grosses cotes verrouillé : aucune fuite, mais meta sûre présente
        $this->assertTrue($items[$grosse->id]['locked']);
        $this->assertSame([], $items[$grosse->id]['evenements']);
        $this->assertSame('equilibre', $items[$grosse->id]['meta']['tier']);
        $this->assertSame('eleve', $items[$grosse->id]['meta']['risque']);
        $this->assertStringNotContainsString('Arsenal', json_encode($items[$grosse->id]));
        $this->assertStringNotContainsString('SECRET99', json_encode($items[$grosse->id]));

        // Buteurs verrouillé : aucune fuite du nom du joueur
        $this->assertTrue($items[$buteur->id]['locked']);
        $this->assertSame([], $items[$buteur->id]['evenements']);
        $this->assertStringNotContainsString('Mbappé', json_encode($items[$buteur->id]));
        $this->assertFalse($items[$buteur->id]['meta']['composition_confirmee']);
    }

    public function test_pack_complet_unlocks_every_vip_category_in_one_subscription(): void
    {
        $gold = $this->sampleSpecialPronostic('Gold', ['match' => 'A vs B', 'pronostic' => '1X', 'cote' => 1.40], 2.80);
        $score = $this->sampleSpecialPronostic('Score', ['match' => 'C vs D', 'score' => '2-1', 'pronostic' => 'Score exact : 2-1', 'cote' => 0], 0);
        $grosse = $this->sampleSpecialPronostic('GrossesCotes', ['match' => 'E vs F', 'pronostic' => 'Over 1.5', 'cote' => 1.60, 'tier' => 'equilibre', 'risque' => 'eleve'], 31.0);
        $carton = $this->sampleSpecialPronostic('Cartons', ['match' => 'G vs H', 'pronostic' => 'Plus de 4,5 cartons', 'cote' => 2.00], 2.00);
        $buteur = $this->sampleSpecialPronostic('Buteurs', ['match' => 'I vs J', 'joueur' => 'Haaland', 'marche' => 'Buteur à tout moment', 'pronostic' => 'Haaland', 'cote' => 1.95], 1.95);

        $user = User::factory()->create(['is_active' => true]);
        app(VipService::class)->grantManual($user->id, AppSetting::ALL_VIP_LEVEL, 30);

        Sanctum::actingAs($user);
        $items = collect($this->getJson('/api/v1/pronostics')->assertOk()->json('pronostics'))->keyBy('id');

        foreach ([$gold, $score, $grosse, $carton, $buteur] as $p) {
            $this->assertFalse($items[$p->id]['locked'], "Niveau {$p->niveau} devrait être débloqué par le Pack Complet.");
            $this->assertTrue($items[$p->id]['subscribed']);
            $this->assertNotEmpty($items[$p->id]['evenements']);
        }
    }

    public function test_pack_complet_subscribers_receive_push_for_any_vip_category(): void
    {
        Http::fake(['https://exp.host/*' => Http::response(['data' => [['status' => 'ok']]])]);

        $packUser = User::factory()->create(['is_active' => true, 'fcm_token' => 'ExponentPushToken[pack]']);
        $freeUser = User::factory()->create(['is_active' => true, 'fcm_token' => 'ExponentPushToken[free]']);
        VipSubscription::create([
            'user_id' => $packUser->id,
            'level' => AppSetting::ALL_VIP_LEVEL,
            'starts_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        $coupon = $this->sampleSpecialPronostic('Cartons', ['match' => 'PSG vs OM', 'pronostic' => '+4.5 cartons', 'cote' => 2.00], 2.00);
        $result = app(VipService::class)->notifyNewCoupon($coupon);

        $this->assertSame(1, $result['subscribers']);
        $this->assertSame(1, $result['others']);
    }

    public function test_special_markets_quality_control_enforces_founders_rules(): void
    {
        $generator = app(SpecialMarketsGenerator::class);

        $payload = [
            'combines' => [
                // Valide : 7 sélections distinctes à ~1.63 => produit ≈ 30.69 (dans [27..35])
                [
                    'niveau' => 'equilibre',
                    'cote_totale' => 99.99, // Volontairement faux : le serveur doit recalculer
                    'risque' => 'faible', // Interdit : doit être forcé à élevé
                    'selections' => [
                        ['championnat' => 'PL', 'match' => 'M1 vs A1', 'marche' => 'DC', 'pronostic' => '1X', 'cote' => 1.60, 'probabilite_estimee' => '68 %', 'justification' => 'Forme domicile.'],
                        ['championnat' => 'Liga', 'match' => 'M2 vs A2', 'marche' => 'Buts', 'pronostic' => '+1.5', 'cote' => 1.65, 'probabilite_estimee' => '65 %', 'justification' => 'Attaques efficaces.'],
                        ['championnat' => 'Serie A', 'match' => 'M3 vs A3', 'marche' => 'BTTS', 'pronostic' => 'Oui', 'cote' => 1.70, 'probabilite_estimee' => '62 %', 'justification' => 'Défenses ouvertes.'],
                        ['championnat' => 'Ligue 1', 'match' => 'M4 vs A4', 'marche' => 'DC', 'pronostic' => '1X', 'cote' => 1.60, 'probabilite_estimee' => '66 %', 'justification' => 'Invaincu chez soi.'],
                        ['championnat' => 'Bundesliga', 'match' => 'M5 vs A5', 'marche' => 'Buts', 'pronostic' => '+2.5', 'cote' => 1.65, 'probabilite_estimee' => '64 %', 'justification' => 'Rythme élevé.'],
                        ['championnat' => 'Eredivisie', 'match' => 'M6 vs A6', 'marche' => 'DC', 'pronostic' => 'X2', 'cote' => 1.60, 'probabilite_estimee' => '65 %', 'justification' => 'Solide à l’extérieur.'],
                        ['championnat' => 'Portugal', 'match' => 'M7 vs A7', 'marche' => 'Buts', 'pronostic' => '+1.5', 'cote' => 1.60, 'probabilite_estimee' => '67 %', 'justification' => 'Moyenne 2.9 buts.'],
                        // Doublon corrélé sur M1 vs A1 : doit être écarté automatiquement
                        ['championnat' => 'PL', 'match' => 'M1 vs A1', 'marche' => 'Buts', 'pronostic' => '+2.5', 'cote' => 1.80, 'probabilite_estimee' => '55 %', 'justification' => 'Doublon.'],
                    ],
                    'points_de_vigilance' => 'Risque élevé malgré des sélections individuelles solides.',
                ],
                // Invalide : seulement 2 sélections
                [
                    'niveau' => 'ambitieux',
                    'selections' => [
                        ['match' => 'X vs Y', 'pronostic' => '1', 'cote' => 1.90, 'probabilite_estimee' => '60 %'],
                        ['match' => 'Z vs W', 'pronostic' => '1', 'cote' => 1.90, 'probabilite_estimee' => '60 %'],
                    ],
                ],
            ],
            'cartons_jaunes' => [
                // Valide : cote 2.05, prob 55% => valeur = +0.128 > 0
                ['match' => 'PSG vs OM', 'pronostic' => 'Plus de 4,5 cartons', 'cote' => 2.05, 'probabilite_estimee' => '55 %', 'confiance' => 'moyenne', 'justification' => 'Classique tendu.'],
                // Rejeté : valeur négative (0.40 * 2.00 - 1 = -0.20)
                ['match' => 'Lyon vs Nice', 'pronostic' => 'Plus de 4,5 cartons', 'cote' => 2.00, 'probabilite_estimee' => '40 %', 'confiance' => 'moyenne', 'justification' => 'Valeur négative.'],
                // Rejeté : cote hors fourchette [1.80..2.30]
                ['match' => 'Lille vs Lens', 'pronostic' => 'Plus de 6,5 cartons', 'cote' => 3.40, 'probabilite_estimee' => '45 %', 'confiance' => 'moyenne', 'justification' => 'Trop risqué.'],
            ],
            'buteurs' => [
                // Valide, mais compo non confirmée => confiance « elevee » abaissée à « moyenne » (Règle #5)
                ['match' => 'Arsenal vs Chelsea', 'joueur' => 'Bukayo Saka', 'marche' => 'Buteur à tout moment', 'cote' => 2.40, 'probabilite_estimee' => '48 %', 'confiance' => 'elevee', 'composition_confirmee' => false, 'justification' => 'Tireur de penalty.'],
                // Rejeté : valeur négative
                ['match' => 'City vs Spurs', 'joueur' => 'Foden', 'marche' => 'Buteur', 'cote' => 2.00, 'probabilite_estimee' => '42 %', 'confiance' => 'moyenne', 'composition_confirmee' => true, 'justification' => 'Insuffisant.'],
            ],
        ];

        $validated = $generator->validateAndNormalize($payload);

        $this->assertCount(1, $validated['combines']);
        $combo = $validated['combines'][0];
        $this->assertSame('equilibre', $combo['tier']);
        $this->assertCount(7, $combo['selections']); // Doublon M1 retiré
        $this->assertEqualsWithDelta(30.33, $combo['cote_totale'], 0.02); // Produit recalculé côté serveur (1.6^4 * 1.65^2 * 1.7)
        $this->assertSame('eleve', $combo['risque']); // Jamais faible

        $this->assertCount(1, $validated['cartons_jaunes']);
        $this->assertSame('PSG vs OM', $validated['cartons_jaunes'][0]['match']);
        $this->assertGreaterThan(0, $validated['cartons_jaunes'][0]['valeur']);

        $this->assertCount(1, $validated['buteurs']);
        $this->assertSame('moyenne', $validated['buteurs'][0]['confiance']); // Règle #5 appliquée
        $this->assertFalse($validated['buteurs'][0]['composition_confirmee']);
    }

    public function test_server_fallback_builds_valid_grosse_cote_combo_from_real_odds(): void
    {
        $generator = app(SpecialMarketsGenerator::class);
        $fixtures = [];

        for ($i = 1; $i <= 8; $i++) {
            $fixtures[] = [
                'id' => 1000 + $i,
                'sport' => 'football',
                'competition' => 'Ligue ' . $i,
                'match' => "Club{$i}A vs Club{$i}B",
                'home' => "Club{$i}A",
                'away' => "Club{$i}B",
                'kickoff' => '18:00',
                'kickoff_at' => now()->addHours(4)->toIso8601String(),
                'selections' => [
                    ['key' => 'home_draw', 'pronostic' => "Double chance Club{$i}A", 'cote' => 1.62],
                    ['key' => 'over', 'line' => 2.5, 'pronostic' => 'Plus de 2,5 buts', 'cote' => 1.78],
                ],
                'data' => [],
            ];
        }

        $combines = $generator->buildServerCombines($fixtures);

        $this->assertNotEmpty($combines);
        $equilibre = collect($combines)->firstWhere('tier', 'equilibre');
        $this->assertNotNull($equilibre);
        $this->assertGreaterThanOrEqual(27.0, $equilibre['cote_totale']);
        $this->assertLessThanOrEqual(35.0, $equilibre['cote_totale']);
        $this->assertGreaterThanOrEqual(5, count($equilibre['selections']));
        $this->assertLessThanOrEqual(9, count($equilibre['selections']));
    }

    public function test_history_win_rate_excludes_grosses_cotes_while_showing_won_hits(): void
    {
        $goldWon = $this->sampleSpecialPronostic('Gold', ['match' => 'A vs B', 'pronostic' => '1X', 'cote' => 1.50], 2.50);
        $goldWon->update(['result_status' => 'won']);

        $grosseLost = $this->sampleSpecialPronostic('GrossesCotes', ['match' => 'C vs D', 'pronostic' => '1X', 'cote' => 1.60], 32.0);
        $grosseLost->update(['result_status' => 'lost']);

        $grosseWon = $this->sampleSpecialPronostic('GrossesCotes', ['match' => 'E vs F', 'pronostic' => '1X', 'cote' => 1.60], 31.5);
        $grosseWon->update(['result_status' => 'won']);

        $response = $this->getJson('/api/v1/pronostics/history')->assertOk();

        // Taux calculé uniquement sur Gold (1/1 = 100 %), pas faussé par les grosses cotes
        $response->assertJsonPath('stats.settled', 1);
        $response->assertJsonPath('stats.won', 1);
        $response->assertJsonPath('stats.win_rate', 100);

        // Mais la grosse cote gagnée apparaît bien dans la liste des coups validés !
        $ids = collect($response->json('pronostics'))->pluck('id')->all();
        $this->assertContains($goldWon->id, $ids);
        $this->assertContains($grosseWon->id, $ids);
        $this->assertNotContains($grosseLost->id, $ids);
    }
}
