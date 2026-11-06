<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Services\FootballDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Formes de réponse vérifiées en direct sur l'API réelle (clé du fondateur) le 2026-11-06 :
 * GET /v4/competitions/PL/standings et GET /v4/teams/57/matches?status=FINISHED&limit=5.
 */
class FootballDataServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_disabled_without_a_key(): void
    {
        $service = app(FootballDataService::class);

        $this->assertFalse($service->covers('soccer_epl'));
        $this->assertNull($service->standings('soccer_epl', 57, 65));
    }

    public function test_it_only_covers_the_leagues_confirmed_free_on_football_data_org(): void
    {
        AppSetting::setValue('football_data_api_key', 'fd-test');
        $service = app(FootballDataService::class);

        $this->assertTrue($service->covers('soccer_epl'));
        $this->assertTrue($service->covers('soccer_uefa_champs_league'));
        // Non couvert par le plan gratuit (vérifié en direct le 2026-11-06) : reste sur Highlightly.
        $this->assertFalse($service->covers('soccer_belgium_first_div'));
        $this->assertFalse($service->covers('soccer_usa_mls'));
        $this->assertFalse($service->covers(null));
    }

    public function test_standings_reads_the_total_table_and_falls_back_cleanly_without_home_away_split(): void
    {
        AppSetting::setValue('football_data_api_key', 'fd-test');

        Http::fake(['api.football-data.org/v4/competitions/PL/standings' => Http::response([
            'standings' => [[
                'stage' => 'REGULAR_SEASON',
                'type' => 'TOTAL',
                'group' => 'Matchday',
                'table' => [
                    ['position' => 1, 'team' => ['id' => 57, 'name' => 'Arsenal FC'], 'playedGames' => 9, 'won' => 8, 'draw' => 0, 'lost' => 1, 'points' => 24, 'goalsFor' => 14, 'goalsAgainst' => 5],
                    ['position' => 2, 'team' => ['id' => 65, 'name' => 'Manchester City FC'], 'playedGames' => 9, 'won' => 7, 'draw' => 0, 'lost' => 2, 'points' => 21, 'goalsFor' => 20, 'goalsAgainst' => 10],
                    ['position' => 20, 'team' => ['id' => 999, 'name' => 'Relégable FC'], 'playedGames' => 9, 'won' => 1, 'draw' => 1, 'lost' => 7, 'points' => 4, 'goalsFor' => 5, 'goalsAgainst' => 20],
                ],
            ]],
        ])]);

        $standings = app(FootballDataService::class)->standings('soccer_epl', 57, 65);

        $this->assertNotNull($standings);
        $this->assertSame(3, $standings['teams']);
        $this->assertSame(24, $standings['leader_points']);
        $this->assertSame(1, $standings['home']['position']);
        $this->assertSame(24, $standings['home']['points']);
        $this->assertSame(['games' => 9, 'wins' => 8, 'draws' => 0, 'loses' => 1, 'scored' => 14, 'received' => 5], $standings['home']['total']);
        $this->assertSame(2, $standings['away']['position']);

        Http::assertSent(fn ($request) => $request->hasHeader('X-Auth-Token', 'fd-test')
            && str_contains($request->url(), '/v4/competitions/PL/standings'));
    }

    public function test_recent_form_reads_full_time_score_and_orientation(): void
    {
        AppSetting::setValue('football_data_api_key', 'fd-test');

        Http::fake(['api.football-data.org/v4/teams/57/matches*' => Http::response([
            'matches' => [
                ['utcDate' => '2026-10-18T15:30:00Z', 'status' => 'FINISHED', 'homeTeam' => ['id' => 351, 'name' => 'Nottingham Forest FC'], 'awayTeam' => ['id' => 57, 'name' => 'Arsenal FC'], 'score' => ['fullTime' => ['home' => 0, 'away' => 1]]],
                ['utcDate' => '2026-10-24T14:00:00Z', 'status' => 'FINISHED', 'homeTeam' => ['id' => 57, 'name' => 'Arsenal FC'], 'awayTeam' => ['id' => 62, 'name' => 'Everton FC'], 'score' => ['fullTime' => ['home' => 2, 'away' => 0]]],
            ],
        ])]);

        $form = app(FootballDataService::class)->recentForm(57);

        $this->assertCount(2, $form);
        // Trié du plus récent au plus ancien.
        $this->assertSame('2026-10-24', $form[0]['date']);
        $this->assertSame('dom', $form[0]['venue']);
        $this->assertSame(2, $form[0]['gf']);
        $this->assertSame('V', $form[0]['result']);
        $this->assertSame('ext', $form[1]['venue']);
        $this->assertSame('V', $form[1]['result']);
    }

    public function test_a_quota_or_network_failure_degrades_to_null_without_throwing(): void
    {
        AppSetting::setValue('football_data_api_key', 'fd-test');
        Http::fake(['api.football-data.org/*' => Http::response(['message' => 'too many requests'], 429)]);

        $service = app(FootballDataService::class);

        $this->assertNull($service->standings('soccer_epl', 57, 65));
        $this->assertNull($service->recentForm(57));
    }
}
