<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Pronostic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PronosticSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AppSetting::setValue('highlightly_api_key', 'highlightly-test');
    }

    private function userWithDevice(): User
    {
        return User::factory()->create([
            'is_active' => true,
            'fcm_token' => 'ExponentPushToken[test-device]',
        ]);
    }

    /**
     * @param array<int, array{id:int, home:string, away:string, score:string, state?:string}> $matches
     */
    private function fakeResults(array $matches): void
    {
        $data = array_map(fn ($m) => [
            'id' => $m['id'],
            'homeTeam' => ['name' => $m['home']],
            'awayTeam' => ['name' => $m['away']],
            'state' => ['description' => $m['state'] ?? 'Finished', 'score' => ['current' => $m['score']]],
        ], $matches);

        Http::fake([
            'soccer.highlightly.net/*' => Http::response(['data' => $data]),
            'exp.host/*' => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]]),
            '*' => Http::response(['data' => []]),
        ]);
    }

    private function pronostic(array $events, array $attributes = []): Pronostic
    {
        return Pronostic::create(array_merge([
            'niveau' => 'Gold',
            'couleur' => '#FFD700',
            'cote_totale' => 4.5,
            'probabilite' => 60,
            'evenements' => $events,
            'match_date' => now()->toDateString(),
            'match_time' => '18:00',
            'team_home' => 'Arsenal',
            'team_away' => 'Chelsea',
            'competition' => 'Combiné Gold',
            'prediction' => 'Combiné 2 événements',
            'odds' => 4.5,
            'confidence' => 60,
            'analysis' => 'Test',
            'is_vip' => true,
            'published_at' => now(),
        ], $attributes));
    }

    public function test_a_combo_whose_legs_all_win_is_validated_and_notified(): void
    {
        $this->userWithDevice();
        $this->fakeResults([
            ['id' => 1, 'home' => 'Arsenal', 'away' => 'Chelsea', 'score' => '2 - 0'],
            ['id' => 2, 'home' => 'PSG', 'away' => 'Lyon', 'score' => '1 - 1'],
        ]);

        $pronostic = $this->pronostic([
            ['match' => 'Arsenal vs Chelsea', 'pronostic' => 'Victoire Arsenal', 'cote' => 1.5, 'fixture_id' => 1, 'home' => 'Arsenal', 'away' => 'Chelsea', 'selection_key' => 'home', 'line' => null],
            ['match' => 'PSG vs Lyon', 'pronostic' => 'Plus de 1,5 but', 'cote' => 1.4, 'fixture_id' => 2, 'home' => 'PSG', 'away' => 'Lyon', 'selection_key' => 'over', 'line' => 1.5],
        ]);

        $this->artisan('binho:settle-pronostics')->assertSuccessful();

        $this->assertSame('won', $pronostic->fresh()->result_status);
        $this->assertNotNull($pronostic->fresh()->result_notified_at);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'exp.host')
            && str_contains($request[0]['body'] ?? '', 'validé')
            && str_contains($request[0]['body'] ?? '', 'Boomm'));
    }

    public function test_double_chance_wins_on_a_draw_and_loses_on_a_defeat(): void
    {
        $this->userWithDevice();
        $this->fakeResults([
            ['id' => 1, 'home' => 'Arsenal', 'away' => 'Chelsea', 'score' => '1 - 1'],
            ['id' => 2, 'home' => 'PSG', 'away' => 'Lyon', 'score' => '2 - 0'],
        ]);

        $won = $this->pronostic([
            ['match' => 'Arsenal vs Chelsea', 'pronostic' => 'Double chance 1X (Arsenal ou nul)', 'cote' => 1.2, 'fixture_id' => 1, 'home' => 'Arsenal', 'away' => 'Chelsea', 'selection_key' => 'home_draw', 'line' => null],
        ]);
        $lost = $this->pronostic([
            ['match' => 'PSG vs Lyon', 'pronostic' => 'Double chance X2 (nul ou Lyon)', 'cote' => 2.1, 'fixture_id' => 2, 'home' => 'PSG', 'away' => 'Lyon', 'selection_key' => 'draw_away', 'line' => null],
        ]);

        $this->artisan('binho:settle-pronostics')->assertSuccessful();

        $this->assertSame('won', $won->fresh()->result_status);
        $this->assertSame('lost', $lost->fresh()->result_status);
    }

    public function test_a_single_losing_leg_loses_the_combo_without_notification(): void
    {
        $this->userWithDevice();
        $this->fakeResults([
            ['id' => 1, 'home' => 'Arsenal', 'away' => 'Chelsea', 'score' => '0 - 2'],
            ['id' => 2, 'home' => 'PSG', 'away' => 'Lyon', 'score' => '1 - 1'],
        ]);

        $pronostic = $this->pronostic([
            ['match' => 'Arsenal vs Chelsea', 'pronostic' => 'Victoire Arsenal', 'cote' => 1.5, 'fixture_id' => 1, 'home' => 'Arsenal', 'away' => 'Chelsea', 'selection_key' => 'home', 'line' => null],
            ['match' => 'PSG vs Lyon', 'pronostic' => 'Plus de 1,5 but', 'cote' => 1.4, 'fixture_id' => 2, 'home' => 'PSG', 'away' => 'Lyon', 'selection_key' => 'over', 'line' => 1.5],
        ]);

        $this->artisan('binho:settle-pronostics')->assertSuccessful();

        $this->assertSame('lost', $pronostic->fresh()->result_status);
        $this->assertNull($pronostic->fresh()->result_notified_at);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'exp.host'));
    }

    public function test_a_combo_with_an_unfinished_match_stays_pending(): void
    {
        $this->fakeResults([
            ['id' => 1, 'home' => 'Arsenal', 'away' => 'Chelsea', 'score' => '2 - 0'],
            ['id' => 2, 'home' => 'PSG', 'away' => 'Lyon', 'score' => '0 - 0', 'state' => 'Second half'],
        ]);

        $pronostic = $this->pronostic([
            ['match' => 'Arsenal vs Chelsea', 'pronostic' => 'Victoire Arsenal', 'cote' => 1.5, 'fixture_id' => 1, 'home' => 'Arsenal', 'away' => 'Chelsea', 'selection_key' => 'home', 'line' => null],
            ['match' => 'PSG vs Lyon', 'pronostic' => 'Victoire PSG', 'cote' => 1.4, 'fixture_id' => 2, 'home' => 'PSG', 'away' => 'Lyon', 'selection_key' => 'home', 'line' => null],
        ]);

        $this->artisan('binho:settle-pronostics')->assertSuccessful();

        $this->assertNull($pronostic->fresh()->result_status);
    }

    public function test_a_combo_without_structured_markets_is_left_for_the_admin(): void
    {
        $this->fakeResults([
            ['id' => 1, 'home' => 'Arsenal', 'away' => 'Chelsea', 'score' => '2 - 0'],
        ]);

        $pronostic = $this->pronostic([
            ['match' => 'Arsenal vs Chelsea', 'pronostic' => 'Les deux équipes marquent', 'cote' => 1.8],
        ]);

        $this->artisan('binho:settle-pronostics')->assertSuccessful();

        $this->assertNull($pronostic->fresh()->result_status);
    }

    public function test_both_teams_to_score_and_double_chance_12_are_settled(): void
    {
        $this->fakeResults([
            ['id' => 1, 'home' => 'Arsenal', 'away' => 'Chelsea', 'score' => '2 - 0'],
        ]);
        $leg = fn (string $key, string $label) => ['match' => 'Arsenal vs Chelsea', 'pronostic' => $label, 'cote' => 1.5, 'fixture_id' => 1, 'home' => 'Arsenal', 'away' => 'Chelsea', 'selection_key' => $key, 'line' => null];

        $twelve = $this->pronostic([$leg('home_away', 'Double chance 12 (Arsenal ou Chelsea)')]);
        $bttsYes = $this->pronostic([$leg('btts_yes', 'Les deux équipes marquent : oui')]);
        $bttsNo = $this->pronostic([$leg('btts_no', 'Les deux équipes marquent : non')]);

        $this->artisan('binho:settle-pronostics')->assertSuccessful();

        $this->assertSame('won', $twelve->fresh()->result_status);
        $this->assertSame('lost', $bttsYes->fresh()->result_status);
        $this->assertSame('won', $bttsNo->fresh()->result_status);
    }

    public function test_a_basketball_coupon_is_settled_with_the_final_scores_and_notified(): void
    {
        $this->userWithDevice();
        AppSetting::setValue('odds_api_key', 'odds-test');
        Http::fake([
            'api.the-odds-api.com/v4/sports/basketball_nba/scores*' => Http::response([[
                'id' => 'nba-1', 'completed' => true, 'home_team' => 'Boston Celtics', 'away_team' => 'Washington Wizards',
                'scores' => [['name' => 'Boston Celtics', 'score' => '118'], ['name' => 'Washington Wizards', 'score' => '101']],
            ]]),
            'exp.host/*' => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]]),
            '*' => Http::response(['data' => []]),
        ]);
        $leg = fn (string $key, ?float $line) => [
            'match' => 'Boston Celtics vs Washington Wizards', 'pronostic' => 'Pari', 'cote' => 1.4, 'sport' => 'basketball',
            'fixture_id' => 'nba-1', 'event_id' => 'nba-1', 'odds_key' => 'basketball_nba', 'home' => 'Boston Celtics',
            'away' => 'Washington Wizards', 'selection_key' => $key, 'line' => $line, 'kickoff_at' => now()->subHours(4)->toIso8601String(),
        ];

        // Victoire de Boston et handicap -8,5 : 118 - 8,5 > 101.
        $pronostic = $this->pronostic([$leg('home', null), $leg('spread_home', -8.5)]);

        $this->artisan('binho:settle-pronostics')->assertSuccessful();

        $this->assertSame('won', $pronostic->fresh()->result_status);
        $this->assertNotNull($pronostic->fresh()->result_notified_at);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'highlightly'));
    }

    public function test_a_coupon_is_not_checked_before_its_matches_can_be_over(): void
    {
        $this->fakeResults([
            ['id' => 1, 'home' => 'Arsenal', 'away' => 'Chelsea', 'score' => '2 - 0'],
        ]);

        $pronostic = $this->pronostic([
            ['match' => 'Arsenal vs Chelsea', 'pronostic' => 'Victoire Arsenal', 'cote' => 1.5, 'fixture_id' => 1, 'home' => 'Arsenal', 'away' => 'Chelsea', 'selection_key' => 'home', 'line' => null, 'kickoff_at' => now()->subMinutes(30)->toIso8601String()],
        ]);

        $this->artisan('binho:settle-pronostics')->assertSuccessful();

        $this->assertNull($pronostic->fresh()->result_status);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'highlightly'));
    }
}
