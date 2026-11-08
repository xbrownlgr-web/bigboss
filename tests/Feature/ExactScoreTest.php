<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Pronostic;
use App\Models\User;
use App\Models\VipSubscription;
use App\Services\ExactScoreGenerator;
use App\Services\PronosticSettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExactScoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Match complet : classement, 5 derniers matchs de chaque équipe, cotes 1N2 et plus/moins 2,5.
     */
    private function fixture(array $overrides = []): array
    {
        $split = fn ($games, $wins, $draws, $loses, $scored, $received) => compact('games', 'wins', 'draws', 'loses', 'scored', 'received');
        $games = fn (array $scores) => array_map(fn ($score) => [
            'date' => '2026-11-01', 'opponent' => 'X', 'venue' => 'dom', 'gf' => $score[0], 'ga' => $score[1],
            'result' => $score[0] > $score[1] ? 'V' : ($score[0] === $score[1] ? 'N' : 'D'),
        ], $scores);

        return array_replace_recursive([
            'id' => 1001, 'league_id' => 33973, 'sport' => 'football', 'match' => 'Arsenal vs Chelsea',
            'competition' => 'Premier League', 'home' => 'Arsenal', 'away' => 'Chelsea',
            'kickoff' => '18:30', 'kickoff_at' => now()->addHours(4)->toIso8601String(),
            'selections' => [
                ['key' => 'home', 'label' => 'Victoire Arsenal', 'cote' => 1.70, 'bookmakers' => 4, 'fair' => 0.56],
                ['key' => 'draw', 'label' => 'Match nul', 'cote' => 4.00, 'bookmakers' => 4, 'fair' => 0.24],
                ['key' => 'away', 'label' => 'Victoire Chelsea', 'cote' => 5.00, 'bookmakers' => 4, 'fair' => 0.20],
                ['key' => 'over', 'label' => 'Plus de 2,5 buts', 'point' => 2.5, 'cote' => 1.95, 'bookmakers' => 4, 'fair' => 0.49],
                ['key' => 'under', 'label' => 'Moins de 2,5 buts', 'point' => 2.5, 'cote' => 1.85, 'bookmakers' => 4, 'fair' => 0.51],
            ],
            'data' => [
                'type' => 'championnat',
                'standings' => [
                    'teams' => 20, 'leader_points' => 24, 'relegation_points' => 6,
                    'home' => ['position' => 1, 'points' => 24, 'total' => $split(10, 8, 0, 2, 20, 8), 'home' => $split(5, 5, 0, 0, 12, 3), 'away' => $split(5, 3, 0, 2, 8, 5)],
                    'away' => ['position' => 9, 'points' => 14, 'total' => $split(10, 4, 2, 4, 13, 14), 'home' => $split(5, 3, 1, 1, 8, 6), 'away' => $split(5, 1, 1, 3, 5, 8)],
                ],
                'form' => [
                    'home' => $games([[2, 0], [3, 1], [1, 0], [2, 1], [1, 1]]),
                    'away' => $games([[0, 1], [1, 1], [2, 2], [0, 2], [1, 0]]),
                ],
                'details' => null,
                'h2h' => null,
            ],
        ], $overrides);
    }

    public function test_the_analysis_is_coherent_with_the_founders_rules(): void
    {
        $analysis = app(ExactScoreGenerator::class)->analyse($this->fixture());

        $this->assertNotNull($analysis);
        $prediction = $analysis['prediction'];

        // 1N2 = 100 %, score n°1 cohérent avec le résultat le plus probable (victoire Arsenal).
        $this->assertSame(100, array_sum($prediction['percent']));
        $this->assertSame('home', collect($prediction['percent'])->sortDesc()->keys()->first());
        $this->assertGreaterThan($prediction['official']['away'], $prediction['official']['home']);
        $this->assertCount(3, $prediction['scores']);
        $this->assertSame($prediction['official']['label'], $prediction['scores'][0]['label']);

        // Grille 0-0 à 5-5 normalisée, aucun score à plus de 20 % ici.
        $grid = app(ExactScoreGenerator::class)->grid($analysis['lambda']['home'], $analysis['lambda']['away']);
        $this->assertEqualsWithDelta(1.0, array_sum(array_map('array_sum', $grid)), 0.0001);
        $this->assertLessThanOrEqual(0.20, $prediction['official']['p']);

        // Compositions et blessés jamais fournis : MOYENNE au mieux, jamais ÉLEVÉE.
        $this->assertSame(ExactScoreGenerator::CONFIDENCE_MEDIUM, $analysis['confidence']);
        $this->assertContains('cotes de scores exacts : donnée manquante (non fournies par l’API de cotes)', $analysis['missing']);
    }

    public function test_dixon_coles_raises_the_draw_scores_compared_to_independent_poisson(): void
    {
        $grid = app(ExactScoreGenerator::class)->grid(1.2, 1.0);
        $independent = fn (int $h, int $a) => exp(-1.2) * 1.2 ** $h / max(1, $h) * exp(-1.0) * 1.0 ** $a;

        // 0-0 et 1-1 plus probables qu'en Poisson indépendant, 1-0 moins.
        $this->assertGreaterThan(exp(-1.2) * exp(-1.0), $grid[0][0]);
        $this->assertGreaterThan(exp(-1.2) * 1.2 * exp(-1.0) * 1.0, $grid[1][1]);
        $this->assertLessThan($independent(1, 0), $grid[1][0]);
    }

    public function test_missing_data_lowers_confidence_and_widens_the_list(): void
    {
        $fixture = $this->fixture();
        $fixture['data']['standings'] = null;
        $analysis = app(ExactScoreGenerator::class)->analyse($fixture);

        $this->assertSame(ExactScoreGenerator::CONFIDENCE_LOW, $analysis['confidence']);
        $this->assertCount(5, $analysis['prediction']['scores']);
    }

    public function test_a_match_without_model_nor_market_is_never_guessed(): void
    {
        $fixture = $this->fixture();
        $fixture['selections'] = [];
        $fixture['data']['standings'] = null;
        $fixture['data']['form'] = ['home' => [], 'away' => []];

        $this->assertNull(app(ExactScoreGenerator::class)->analyse($fixture));
    }

    public function test_ai_adjustments_are_bounded_and_need_a_reason(): void
    {
        AppSetting::setValue('ai_exact_score_instructions', 'Méthode test.');
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'ajustements' => [
                ['equipe' => 'domicile', 'delta' => -2.5, 'raison' => 'Buteur absent selon l’actualité du dossier'],
                ['equipe' => 'exterieur', 'delta' => 0.3, 'raison' => ''],
            ],
            'facteurs_cles' => ['Arsenal invaincu à domicile'],
            'donnees_manquantes' => ['xG'],
            'risque_principal' => 'Carton rouge précoce.',
        ])]]]])]);

        $generator = app(ExactScoreGenerator::class);
        $analysis = $generator->analyse($this->fixture());
        $ai = $generator->askAi('sk-test', $analysis);
        $final = $generator->finalize($analysis, $ai['adjustments']);

        // −2,5 ramené à −0,40 ; l'ajustement sans raison est ignoré.
        $this->assertCount(1, $final['adjustments']);
        $this->assertSame(-0.4, $final['adjustments'][0]['delta']);
        $this->assertEqualsWithDelta($analysis['base']['home'] - 0.4, $final['lambda']['home'], 0.001);
        $this->assertSame($analysis['base']['away'], $final['lambda']['away']);

        $text = $generator->render($final, $ai);
        $this->assertStringStartsWith('🎯 BIG BOSS — SCORE EXACT', $text);
        $this->assertStringContainsString('Top 3 scores exacts :', $text);
        $this->assertStringContainsString('Arsenal invaincu à domicile', $text);
        $this->assertStringContainsString('−0,40 but Arsenal', $text);
        $this->assertStringContainsString('il peut perdre', $text);
        Http::assertSent(fn ($request) => str_contains($request['messages'][0]['content'], 'Méthode test.')
            && str_contains($request['messages'][0]['content'], 'AUCUN outil de recherche')
            && str_contains($request['messages'][1]['content'], 'Arsenal vs Chelsea'));
    }

    public function test_the_exact_score_is_won_only_on_the_exact_final_score(): void
    {
        $settlement = app(PronosticSettlementService::class);
        $pronostic = new Pronostic(['evenements' => [[
            'selection_key' => 'exact_score', 'fixture_id' => 7, 'score_home' => 2, 'score_away' => 1,
        ]]]);
        $result = fn ($home, $away) => [7 => ['home_goals' => $home, 'away_goals' => $away, 'finished' => true]];

        $this->assertSame('won', $settlement->settle($pronostic, $result(2, 1)));
        $this->assertSame('lost', $settlement->settle($pronostic, $result(1, 0)));
        $this->assertSame('lost', $settlement->settle($pronostic, $result(1, 2)));
    }

    public function test_lost_exact_scores_stay_out_of_the_history_and_the_win_rate(): void
    {
        $make = fn (string $niveau, string $result) => Pronostic::create([
            'niveau' => $niveau, 'couleur' => '#000000', 'cote_totale' => $niveau === 'Score' ? null : 2.0, 'probabilite' => 12,
            'evenements' => [['match' => "{$niveau} {$result}", 'score' => '1-0']],
            'match_date' => now()->toDateString(), 'match_time' => '18:00', 'team_home' => 'A', 'team_away' => 'B',
            'competition' => 'Test', 'prediction' => 'Test', 'odds' => 0, 'confidence' => 12, 'analysis' => '',
            'is_vip' => $niveau !== 'Starter', 'published_at' => now()->subDay(), 'result_status' => $result,
        ]);
        $make('Starter', 'won');
        $make('Starter', 'lost');
        $make('Score', 'won');
        $make('Score', 'lost');

        $history = $this->getJson('/api/v1/pronostics/history')->assertOk();

        $this->assertSame(2, $history->json('stats.settled'));
        $this->assertSame(50, $history->json('stats.win_rate'));
        $listed = collect($history->json('pronostics'))->map(fn ($item) => $item['niveau'] . ' ' . $item['result_status'])->sort()->values()->all();
        $this->assertSame(['Score won', 'Starter lost', 'Starter won'], $listed);
    }

    public function test_the_score_offer_is_a_vip_offer_at_5000_and_locked_for_non_subscribers(): void
    {
        $offers = $this->getJson('/api/v1/settings/public')->assertOk()->json('vip.offers');
        $this->assertSame(5000, $offers['Score']['price']);
        $this->assertStringContainsString('peut perdre', $offers['Score']['details']);

        $pronostic = Pronostic::create([
            'niveau' => 'Score', 'couleur' => '#BE123C', 'cote_totale' => null, 'probabilite' => 13,
            'evenements' => [['match' => 'Arsenal vs Chelsea', 'score' => '2-1', 'selection_key' => 'exact_score']],
            'match_date' => now()->toDateString(), 'match_time' => '18:00', 'team_home' => 'Arsenal', 'team_away' => 'Chelsea',
            'competition' => 'Score exact', 'prediction' => 'Score exact : 2-1', 'odds' => 0, 'confidence' => 13,
            'analysis' => 'Analyse', 'is_vip' => true, 'published_at' => now(),
        ]);

        $locked = collect($this->getJson('/api/v1/pronostics')->json('pronostics'))->firstWhere('id', $pronostic->id);
        $this->assertTrue($locked['locked']);
        $this->assertSame([], $locked['evenements']);

        $member = User::factory()->create(['is_active' => true]);
        VipSubscription::create(['user_id' => $member->id, 'level' => 'Score', 'starts_at' => now(), 'ends_at' => now()->addDays(30)]);
        Sanctum::actingAs($member);

        $open = collect($this->getJson('/api/v1/pronostics')->json('pronostics'))->firstWhere('id', $pronostic->id);
        $this->assertFalse($open['locked']);
        $this->assertSame('2-1', $open['evenements'][0]['score']);
    }

    public function test_a_won_exact_score_is_shown_to_everyone_as_proof_but_not_its_analysis(): void
    {
        $make = fn (?string $result) => Pronostic::create([
            'niveau' => 'Score', 'couleur' => '#BE123C', 'cote_totale' => null, 'probabilite' => 13,
            'evenements' => [['match' => 'Arsenal vs Chelsea', 'score' => '2-1', 'selection_key' => 'exact_score']],
            'match_date' => now()->toDateString(), 'match_time' => '18:00', 'team_home' => 'Arsenal', 'team_away' => 'Chelsea',
            'competition' => 'Score exact', 'prediction' => 'Score exact : 2-1', 'odds' => 0, 'confidence' => 13,
            'analysis' => 'Analyse secrète', 'is_vip' => true, 'published_at' => now(), 'result_status' => $result,
        ]);
        $won = $make('won');
        $pending = $make(null);

        $items = collect($this->getJson('/api/v1/pronostics')->json('pronostics'))->keyBy('id');

        $this->assertTrue($items[$won->id]['locked']);
        $this->assertSame('2-1', $items[$won->id]['evenements'][0]['score']);
        $this->assertSame('', $items[$won->id]['analysis']);
        $this->assertSame([], $items[$pending->id]['evenements']);
        $this->assertSame('', $items[$pending->id]['analysis']);
    }

    public function test_the_slot_runner_starts_the_exact_score_search_at_its_own_hours(): void
    {
        AppSetting::setValue('ai_schedule_score', '10:30');
        AppSetting::setValue('highlightly_api_key', '');
        foreach (\App\Support\PronosticRules::LEVELS as $level) {
            AppSetting::setValue('ai_schedule_' . strtolower($level), '');
        }

        $this->travelTo(\Illuminate\Support\Carbon::parse('today 10:35', 'Africa/Porto-Novo'));
        $this->artisan('binho:run-pronostic-slots')->expectsOutputToContain('Créneau 10:30 : Score')->assertSuccessful();

        $run = \App\Models\PronosticRun::query()->where('level', 'Score')->sole();
        // Sans clé Highlightly : aucun match, rien d'inventé, et le créneau le dit.
        $this->assertSame(\App\Models\PronosticRun::STATUS_ERROR, $run->status);
        $this->assertSame(0, Pronostic::query()->where('niveau', 'Score')->count());
    }
}
