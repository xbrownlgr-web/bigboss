<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateDailyPronostics;
use App\Exceptions\NoValueBetException;
use App\Models\AppSetting;
use App\Models\Pronostic;
use App\Models\PronosticRun;
use App\Models\User;
use App\Services\AiPronosticGenerator;
use App\Services\ValueAnalyzer;
use App\Support\PronosticRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiPronosticTest extends TestCase
{
    use RefreshDatabase;

    /** Faux matchs de football renvoyés par Highlightly (false : aucun match). */
    private bool $football = true;

    /** @var array<int, array<string, mixed>> rencontres NBA renvoyées par la fausse The Odds API */
    private array $nba = [];

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@binhoflash.com', 'is_active' => true]);
    }

    private function configureKeys(bool $football = true): void
    {
        AppSetting::setValue('openrouter_api_key', 'sk-or-test');
        AppSetting::setValue('highlightly_api_key', $football ? 'highlightly-test' : '');
        AppSetting::setValue('odds_api_key', 'odds-test');
    }

    /**
     * Arsenal - Chelsea (Premier League), PSG - Lyon (Ligue 1) et Real Madrid - Getafe (Liga) :
     * favoris nets, trois bookmakers ou plus, classement, 5 derniers matchs, H2H et modèle
     * Highlightly. Seules des double chances passent les seuils stricts (75 % / 80 %).
     */
    private function fakeServices(?array $aiResponse = null): void
    {
        $aiResponse ??= [
            'coupon' => [
                'selections' => [
                    ['id' => 'C5', 'confiance' => 'Élevée'],
                    ['id' => 'C99', 'confiance' => 'Moyenne'],
                    ['id' => 'C6', 'confiance' => 'Moyenne'],
                    ['id' => 'C7', 'confiance' => 'Moyenne'],
                ],
                'resume' => 'Trois double chances sur des favoris solides à domicile.',
            ],
            'analyses' => [
                'C5' => ['raisons' => ['Arsenal 2e, 7 victoires en 8 matchs à domicile', 'Chelsea a perdu 4 de ses 5 derniers matchs', 'raison de trop'], 'risques' => 'Blessés : donnée manquante.'],
            ],
            'ecartees' => [],
        ];

        Http::fake([
            'soccer.highlightly.net/*' => fn ($request) => $this->highlightly($request),
            'api.the-odds-api.com/*' => fn ($request) => $this->odds($request),
            'openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode($aiResponse)]]]]),
            '*' => Http::response(['data' => []]),
        ]);
    }

    private function highlightly($request)
    {
        $path = parse_url($request->url(), PHP_URL_PATH);
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return match (true) {
            // Une seule page de matchs (pagination par offset).
            $path === '/matches' => Http::response(['data' => $this->football && (int) ($query['offset'] ?? 0) === 0 ? [
                $this->fixture(1001, 33973, 'Arsenal', 'Chelsea', 10, 20),
                $this->fixture(1002, 52695, 'PSG', 'Lyon', 30, 40),
                $this->fixture(1004, 119924, 'Real Madrid', 'Getafe', 50, 60),
                $this->fixture(1003, 999999, 'Club amateur', 'Autre club', 70, 80),
            ] : []]),
            $path === '/standings' => Http::response(['groups' => [['name' => 'Saison', 'standings' => match ((int) $query['leagueId']) {
                33973 => [
                    $this->standing(99, 1, 22, [8, 7, 1, 0, 18, 4], [8, 5, 1, 2, 12, 9]),
                    $this->standing(10, 2, 20, [8, 7, 0, 1, 20, 5], [8, 4, 2, 2, 10, 8]),
                    $this->standing(20, 15, 11, [8, 3, 2, 3, 9, 10], [8, 1, 1, 6, 6, 15]),
                ],
                119924 => [
                    $this->standing(50, 1, 23, [8, 7, 1, 0, 19, 5], [8, 5, 1, 2, 13, 9]),
                    $this->standing(60, 16, 10, [8, 3, 2, 3, 8, 10], [8, 1, 2, 5, 5, 13]),
                ],
                default => [
                    $this->standing(30, 1, 24, [8, 7, 1, 0, 21, 4], [8, 5, 2, 1, 14, 7]),
                    $this->standing(40, 6, 14, [8, 4, 2, 2, 12, 9], [8, 2, 2, 4, 8, 12]),
                ],
            }]]]),
            $path === '/last-five-games' => Http::response(match ((int) $query['teamId']) {
                10 => $this->games(10, [[2, 0], [3, 1], [1, 1], [2, 0], [3, 0]]),
                20 => $this->games(20, [[0, 2], [1, 3], [0, 1], [1, 1], [0, 2]]),
                30 => $this->games(30, [[3, 0], [2, 1], [4, 1], [1, 0], [2, 2]]),
                50 => $this->games(50, [[2, 0], [3, 1], [2, 1], [1, 0], [2, 0]]),
                60 => $this->games(60, [[0, 1], [1, 2], [0, 2], [1, 1], [0, 1]]),
                default => $this->games(40, [[1, 1], [2, 1], [0, 1], [1, 0], [2, 2]]),
            }),
            $path === '/head-2-head' => Http::response($this->games((int) $query['teamIdOne'], [[2, 1], [1, 1], [0, 1]])),
            $path === '/matches/1001' => Http::response([[
                'referee' => ['name' => 'Michael Oliver'],
                'venue' => ['name' => 'Emirates Stadium', 'city' => 'Londres'],
                'forecast' => ['status' => 'Nuageux', 'temperature' => '14°C'],
                'predictions' => ['prematch' => [['generatedAt' => now()->toIso8601String(), 'probabilities' => ['home' => '70%', 'draw' => '18%', 'away' => '12%']]]],
                'news' => [['title' => 'Arsenal récupère son buteur', 'datePublished' => now()->subHours(5)->toIso8601String()]],
            ]]),
            default => Http::response(['data' => []]),
        };
    }

    /**
     * Fausse The Odds API : une rencontre par championnat, les rencontres NBA du test, rien ailleurs.
     */
    private function odds($request)
    {
        $path = (string) parse_url($request->url(), PHP_URL_PATH);

        $events = match (true) {
            str_contains($path, '/soccer_epl/odds') => [$this->oddsEvent('Arsenal', 'Chelsea', ['home' => [1.68, 1.70, 1.72], 'draw' => [4.0, 4.1, 3.9], 'away' => [5.5, 5.4, 5.6]], ['over' => [1.8, 1.82, 1.81], 'under' => [2.0, 1.98, 1.99]])],
            str_contains($path, '/soccer_france_ligue_one/odds') => [$this->oddsEvent('PSG', 'Lyon', ['home' => [1.5, 1.52, 1.51], 'draw' => [4.2, 4.3, 4.25], 'away' => [6.0, 6.2, 6.1]], ['over' => [1.7, 1.72, 1.71], 'under' => [2.1, 2.08, 2.09]])],
            str_contains($path, '/soccer_spain_la_liga/odds') => [$this->oddsEvent('Real Madrid', 'Getafe', ['home' => [1.80, 1.82, 1.78], 'draw' => [3.8, 3.75, 3.85], 'away' => [5.0, 5.1, 4.9]], ['over' => [1.85, 1.86, 1.84], 'under' => [1.95, 1.94, 1.96]])],
            str_contains($path, '/basketball_nba/odds') => $this->nba,
            default => [],
        };

        return Http::response($events, 200, ['x-requests-remaining' => '450', 'x-requests-used' => '50', 'x-requests-last' => '2']);
    }

    private function fixture(int $id, int $leagueId, string $home, string $away, int $homeId, int $awayId): array
    {
        return [
            'id' => $id,
            'date' => now()->addHours(3)->toIso8601String(),
            'league' => ['id' => $leagueId, 'season' => 2026],
            'homeTeam' => ['id' => $homeId, 'name' => $home],
            'awayTeam' => ['id' => $awayId, 'name' => $away],
            'state' => ['description' => 'Not started'],
        ];
    }

    /**
     * @param array{0:int,1:int,2:int,3:int,4:int,5:int} $home matchs, V, N, D, buts pour, buts contre
     */
    private function standing(int $teamId, int $position, int $points, array $home, array $away): array
    {
        $split = fn ($row) => ['games' => $row[0], 'wins' => $row[1], 'draws' => $row[2], 'loses' => $row[3], 'scoredGoals' => $row[4], 'receivedGoals' => $row[5]];
        $total = array_map(fn ($a, $b) => $a + $b, $home, $away);

        return ['team' => ['id' => $teamId, 'name' => "Équipe {$teamId}"], 'position' => $position, 'points' => $points, 'total' => $split($total), 'home' => $split($home), 'away' => $split($away)];
    }

    /**
     * @param array<int, array{0:int,1:int}> $scores buts de l'équipe, buts de l'adversaire
     */
    private function games(int $teamId, array $scores): array
    {
        return collect($scores)->map(fn ($score, $index) => [
            'date' => now()->subDays(7 * ($index + 1))->toIso8601String(),
            'homeTeam' => ['id' => $teamId, 'name' => "Équipe {$teamId}"],
            'awayTeam' => ['id' => 900 + $index, 'name' => 'Adversaire ' . ($index + 1)],
            'state' => ['description' => 'Finished', 'score' => ['current' => "{$score[0]} - {$score[1]}"]],
        ])->all();
    }

    /**
     * Rencontre NBA cotée par trois bookmakers (vainqueur seulement).
     */
    private function nbaEvent(string $id, string $home, string $away, array $homePrices, array $awayPrices): array
    {
        return [
            'id' => $id,
            'commence_time' => now()->addHours(4)->toIso8601String(),
            'home_team' => $home,
            'away_team' => $away,
            'bookmakers' => collect($homePrices)->map(fn ($price, $i) => ['key' => "book{$i}", 'title' => "Book {$i}", 'markets' => [
                ['key' => 'h2h', 'outcomes' => [['name' => $home, 'price' => $price], ['name' => $away, 'price' => $awayPrices[$i]]]],
            ]])->all(),
        ];
    }

    /**
     * Sélection déjà analysée (pour tester la composition sans dépendre des API).
     */
    private function candidate(int $n, string $key, float $cote, float $p, ?float $market = null): array
    {
        return [
            'id' => "C{$n}", 'match' => "Équipe {$n} vs Adversaire {$n}", 'competition' => 'Premier League', 'pronostic' => "Pari {$n}",
            'cote' => $cote, 'meilleure_cote' => $cote, 'bookmakers' => 3, 'fixture_id' => 5000 + $n, 'event_id' => null,
            'sport' => 'football', 'odds_key' => 'soccer_epl', 'league_id' => 33973, 'kickoff' => '18:00',
            'kickoff_at' => now()->addHours(4)->toIso8601String(), 'home' => "Équipe {$n}", 'away' => "Adversaire {$n}",
            'selection_key' => $key, 'line' => in_array($key, ['over', 'under'], true) ? 2.5 : null, 'categorie' => 'solide',
            'probabilite' => $p, 'prob_implicite' => 1 / $cote, 'prob_marche' => $market ?? $p - 0.02, 'prob_poisson' => $p + 0.02,
            'prob_highlightly' => null, 'valeur' => $p * $cote - 1, 'confiance' => 'Moyenne', 'risques' => [],
        ];
    }

    /**
     * Six sélections à 80 % ou plus (VIP), cinq entre 75 et 79 % (gratuits seulement), et trois
     * interdites : victoire sèche d'un favori pas assez net, nul sec.
     *
     * @return array<int, array<string, mixed>>
     */
    private function candidatePool(): array
    {
        return [
            $this->candidate(1, 'home_draw', 1.45, 0.86),
            $this->candidate(2, 'home_draw', 1.50, 0.84),
            $this->candidate(3, 'over', 1.42, 0.82),
            $this->candidate(4, 'draw_away', 1.40, 0.83),
            $this->candidate(5, 'under', 1.48, 0.81),
            $this->candidate(6, 'home_away', 1.44, 0.80),
            $this->candidate(7, 'home', 1.60, 0.70),
            $this->candidate(8, 'draw', 3.20, 0.31),
            $this->candidate(9, 'home', 1.30, 0.85, 0.83),
            $this->candidate(10, 'home_draw', 1.45, 0.78),
            $this->candidate(11, 'over', 1.50, 0.77),
            $this->candidate(12, 'home_away', 1.42, 0.76),
            $this->candidate(13, 'under', 1.44, 0.79),
            $this->candidate(14, 'btts_no', 1.55, 0.76),
        ];
    }

    private function fakeCandidates(array $candidates): void
    {
        $this->mock(ValueAnalyzer::class, function ($mock) use ($candidates) {
            $mock->shouldReceive('candidates')->andReturn($candidates);
            $mock->shouldReceive('fallbackCandidates')->andReturn([]);
            $mock->shouldReceive('analyseFixture')->andReturn(['expected' => null, 'missing' => [], 'selections' => []]);
        });
    }

    public function test_the_ai_receives_the_collected_data_and_invented_selections_are_dropped(): void
    {
        $this->configureKeys();
        $this->fakeServices();

        $this->actingAs($this->admin())
            ->post('/admin/pronostics/generate', ['date' => now()->addHours(3)->toDateString(), 'source' => 'auto', 'levels' => ['Bronze']])
            ->assertSessionHas('success');

        $drafts = session('ai_drafts');
        $this->assertCount(1, $drafts);
        $this->assertSame('Bronze', $drafts[0]['niveau']);

        // C99 (inventée) est écartée ; cotes et probabilités viennent du serveur, pas de l'IA.
        // Bronze = 2 événements (échelle BIG BOSS — voir CLAUDE.md).
        $events = $drafts[0]['evenements'];
        $this->assertCount(2, $events);
        $this->assertSame('Double chance 1X (Arsenal ou nul)', $events[0]['pronostic']);
        $this->assertSame('home_draw', $events[0]['selection_key']);
        $this->assertSame(1001, $events[0]['fixture_id']);
        $this->assertSame(33973, $events[0]['league_id']);
        $this->assertNotEmpty($events[0]['kickoff_at']);
        $this->assertEqualsWithDelta(1.19, $events[0]['cote'], 0.001);
        // L'IA ne peut pas relever la confiance calculée (Moyenne), et 2 raisons au maximum.
        $this->assertSame('Moyenne', $events[0]['confiance']);
        $this->assertCount(2, $events[0]['raisons']);

        foreach ($events as $event) {
            $this->assertGreaterThanOrEqual(80, $event['probabilite']);
        }

        // Bronze est VIP (voir CLAUDE.md) : cote comprise dans sa fourchette ([1,45 ; 2,10]).
        $this->assertGreaterThanOrEqual(1.45, $drafts[0]['cote_totale']);
        $this->assertStringStartsWith('BIG BOSS — VIP BRONZE', $drafts[0]['analysis']);
        $this->assertStringContainsString('Trois double chances', $drafts[0]['analysis']);
        $this->assertStringContainsString(AiPronosticGenerator::DISCLAIMER, $drafts[0]['analysis']);

        // Victoire simple d'un favori pas assez net : jamais proposée à l'IA.
        Http::assertSent(fn ($request) => str_contains($request->url(), 'openrouter.ai')
            && str_contains($request['messages'][1]['content'], 'Emirates Stadium')
            && str_contains($request['messages'][1]['content'], 'Forme Arsenal (5 derniers matchs)')
            && str_contains($request['messages'][1]['content'], 'blessés et suspendus : donnée manquante')
            && str_contains($request['messages'][1]['content'], 'GROUPE : Bronze (VIP payant)')
            && str_contains($request['messages'][1]['content'], 'd’au moins 80 %')
            && ! str_contains($request['messages'][1]['content'], '| Victoire Arsenal |')
            && ! str_contains($request['messages'][1]['content'], 'Club amateur'));
    }

    public function test_a_coupon_is_still_published_when_the_strict_rules_find_nothing(): void
    {
        $this->configureKeys();
        $this->fakeServices();

        // Silver : 80 % par sélection ; seules deux double chances à ~1,20 l'atteignent, ce qui ne
        // suffit pas à former un combiné à la cote minimale. Un coupon doit sortir à chaque créneau :
        // le seuil est abaissé par paliers, et le coupon le dit en tête de son analyse.
        $this->artisan('binho:generate-pronostics', ['--level' => ['Silver'], '--slot' => '10:00'])->assertSuccessful();

        $silver = Pronostic::query()->where('niveau', 'Silver')->sole();
        $this->assertMatchesRegularExpression('/Fiabilité ajustée|Coupon de secours/', $silver->analysis);
        $run = PronosticRun::query()->where('level', 'Silver')->sole();
        $this->assertSame(PronosticRun::STATUS_PUBLISHED, $run->status);
        $this->assertMatchesRegularExpression('/seuil de fiabilité abaissé|coupon de secours/', $run->message);
        $this->assertSame('success', Cache::get(GenerateDailyPronostics::LAST_RUN_CACHE_KEY)['status']);
    }

    public function test_double_chance_is_derived_from_real_odds(): void
    {
        $this->configureKeys();
        $this->fakeServices();

        $fixtures = app(\App\Services\OddsService::class)->attach(app(\App\Services\FixtureService::class)->upcoming(now(), now()->addHours(18)));
        $selections = collect(collect($fixtures)->firstWhere('match', 'Arsenal vs Chelsea')['selections'])->keyBy('key');

        // 1 / (1/1,70 + 1/4,0) ≈ 1,19 : la cote prudente garde la marge du bookmaker.
        $this->assertEqualsWithDelta(1.19, $selections['home_draw']['cote'], 0.02);
        $this->assertTrue($selections['home_draw']['derived']);
        // 1 / (1/1,70 + 1/5,5) ≈ 1,30.
        $this->assertEqualsWithDelta(1.30, $selections['home_away']['cote'], 0.02);
    }

    public function test_manual_matches_cannot_be_verified_so_no_bet_is_recommended(): void
    {
        $this->configureKeys(football: false);
        $this->fakeServices();

        $this->actingAs($this->admin())
            ->post('/admin/pronostics/generate', [
                'date' => now()->toDateString(),
                'source' => 'manual',
                'manual_matches' => "Arsenal vs Chelsea | Premier League | 18:30\nPSG vs Lyon",
            ])
            ->assertSessionHas('error', fn ($message) => str_starts_with($message, 'Aucun pari recommandé'));

        $this->assertNull(session('ai_drafts'));
    }

    public function test_a_missing_openrouter_key_is_explained(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/pronostics/generate', ['date' => now()->toDateString(), 'source' => 'manual', 'manual_matches' => "A vs B\nC vs D"])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'OpenRouter'));
    }

    public function test_publishing_a_draft_keeps_real_codes_only_and_removes_the_draft(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);
        $admin = $this->admin();

        $this->actingAs($admin)
            ->withSession(['ai_drafts' => [['niveau' => 'Starter', 'match_date' => now()->toDateString(), 'evenements' => [], 'cote_totale' => 2, 'probabilite' => 80, 'analysis' => '']]])
            ->post('/admin/pronostics', [
                'draft' => 0,
                'niveau' => 'Starter',
                'match_date' => now()->toDateString(),
                'cote_totale' => 2.1,
                'probabilite' => 80,
                'evenements_text' => "Arsenal vs Chelsea | Plus de 1,5 but | 1.40\nPSG vs Lyon | Victoire PSG | 1.50",
            ])
            ->assertSessionHas('success');

        $pronostic = Pronostic::query()->firstOrFail();
        $this->assertNull($pronostic->code_1xbet);
        $this->assertNull($pronostic->code_melbet);
        $this->assertSame([], session('ai_drafts'));
    }

    public function test_the_pronostics_page_shows_drafts(): void
    {
        $this->actingAs($this->admin())
            ->withSession(['ai_drafts' => [[
                'niveau' => 'Gold',
                'match_date' => now()->toDateString(),
                'evenements' => [['match' => 'Arsenal vs Chelsea', 'pronostic' => 'Victoire Arsenal', 'cote' => 1.8]],
                'cote_totale' => 1.8,
                'probabilite' => 55,
                'analysis' => 'Analyse test',
            ]]])
            ->get('/admin/pronostics')
            ->assertOk()
            ->assertSee('Propositions de l’IA à vérifier', false)
            ->assertSee('Arsenal vs Chelsea | Victoire Arsenal | 1.80');
    }

    public function test_the_command_publishes_a_bronze_coupon_in_the_mandatory_format(): void
    {
        $this->configureKeys();
        $this->fakeServices();

        $this->artisan('binho:generate-pronostics', ['--level' => ['Bronze'], '--slot' => '16:30'])->assertSuccessful();

        $pronostic = Pronostic::query()->sole();
        $this->assertSame('Bronze', $pronostic->niveau);
        // Bronze est VIP (voir CLAUDE.md), 2 événements (échelle progressive BIG BOSS).
        $this->assertTrue($pronostic->is_vip);
        $this->assertSame('VIP Bronze', $pronostic->competition);
        $this->assertCount(2, $pronostic->evenements);
        $this->assertGreaterThanOrEqual(1.45, $pronostic->cote_totale);

        foreach ($pronostic->evenements as $event) {
            // Tout ce qu'il faut pour valider le résultat automatiquement après les matchs.
            $this->assertNotEmpty($event['selection_key']);
            $this->assertNotEmpty($event['fixture_id']);
            $this->assertNotEmpty($event['league_id']);
            $this->assertNotEmpty($event['kickoff_at']);
        }

        $this->assertStringStartsWith('BIG BOSS — VIP BRONZE', $pronostic->analysis);
        $this->assertStringContainsString('coupon de 16:30', $pronostic->analysis);
        $this->assertStringContainsString(AiPronosticGenerator::DISCLAIMER, $pronostic->analysis);

        $run = PronosticRun::query()->sole();
        $this->assertSame(['Bronze', '16:30', PronosticRun::STATUS_PUBLISHED, $pronostic->id], [$run->level, $run->slot, $run->status, $run->pronostic_id]);
        $this->assertSame(1, \Illuminate\Support\Facades\DB::table('scheduled_pronostic_notifications')->count());
        $this->assertSame('success', Cache::get(GenerateDailyPronostics::LAST_RUN_CACHE_KEY)['status']);
    }

    public function test_a_dry_run_shows_the_coupon_without_publishing(): void
    {
        $this->configureKeys();
        $this->fakeServices();

        $this->artisan('binho:generate-pronostics', ['--level' => ['Bronze'], '--dry-run' => true])
            ->expectsOutputToContain('Arsenal vs Chelsea')
            ->expectsOutputToContain('rien n’a été publié')
            ->assertSuccessful();

        $this->assertSame(0, Pronostic::query()->count());
        $this->assertSame(0, PronosticRun::query()->count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'exp.host'));
    }

    public function test_publishing_an_ai_draft_keeps_what_the_automatic_settlement_needs(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);
        $draftEvent = fn (string $match, string $pick, string $key) => [
            'match' => $match, 'pronostic' => $pick, 'cote' => 1.7, 'fixture_id' => 1001,
            'home' => 'Arsenal', 'away' => 'Chelsea', 'selection_key' => $key, 'line' => null,
            'probabilite' => 63, 'confiance' => 'Moyenne',
        ];

        $this->actingAs($this->admin())
            ->withSession(['ai_drafts' => [[
                'niveau' => 'Starter', 'match_date' => now()->toDateString(), 'cote_totale' => 2.55, 'probabilite' => 40, 'analysis' => '',
                'evenements' => [$draftEvent('Arsenal vs Chelsea', 'Victoire Arsenal', 'home'), $draftEvent('PSG vs Lyon', 'Victoire PSG', 'home')],
            ]]])
            ->post('/admin/pronostics', [
                'draft' => 0,
                'niveau' => 'Starter',
                'match_date' => now()->toDateString(),
                'cote_totale' => 2.55,
                'probabilite' => 40,
                // L'admin garde le premier pari mais change le second.
                'evenements_text' => "Arsenal vs Chelsea | Victoire Arsenal | 1.70\nPSG vs Lyon | Match nul | 1.50",
            ])
            ->assertSessionHas('success');

        [$kept, $edited] = Pronostic::query()->firstOrFail()->evenements;
        $this->assertSame(1001, $kept['fixture_id']);
        $this->assertSame('home', $kept['selection_key']);
        $this->assertSame('Match nul', $edited['pronostic']);
        $this->assertArrayNotHasKey('selection_key', $edited);
    }

    public function test_the_server_composes_the_coupon_when_the_ai_proposes_nothing(): void
    {
        $this->configureKeys();
        $this->fakeServices(['coupon' => ['selections' => []], 'analyses' => [], 'ecartees' => []]);

        $this->artisan('binho:generate-pronostics', ['--level' => ['Bronze']])->assertSuccessful();

        $rules = PronosticRules::for('Bronze');
        $pronostic = Pronostic::query()->sole();
        // Un combiné valable : au moins 2 sélections, dans la fourchette de cote du groupe.
        $this->assertGreaterThanOrEqual(2, count($pronostic->evenements));
        $this->assertLessThanOrEqual($rules['max_selections'], count($pronostic->evenements));
        $this->assertGreaterThanOrEqual($rules['min_odds'], (float) $pronostic->cote_totale);
        $this->assertLessThanOrEqual($rules['max_odds'], (float) $pronostic->cote_totale);
        $this->assertNotEmpty($pronostic->evenements[0]['raisons']);
        $this->assertSame('success', Cache::get(GenerateDailyPronostics::LAST_RUN_CACHE_KEY)['status']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'openrouter.ai'));
    }

    public function test_selections_vetoed_by_the_ai_are_never_published(): void
    {
        $this->configureKeys();
        $vetoes = collect(range(1, 30))->map(fn ($n) => ['id' => "C{$n}", 'raison' => 'Derby et cote en baisse'])->all();
        $this->fakeServices(['coupon' => ['selections' => []], 'analyses' => [], 'ecartees' => $vetoes]);

        $this->artisan('binho:generate-pronostics', ['--level' => ['Bronze']])
            ->expectsOutputToContain('Rien de fiable pour Bronze')
            ->assertSuccessful();

        $this->assertSame(0, Pronostic::query()->count());
        $this->assertSame(PronosticRun::STATUS_EMPTY, PronosticRun::query()->sole()->status);
        $this->assertSame('empty', Cache::get(GenerateDailyPronostics::LAST_RUN_CACHE_KEY)['status']);
    }

    public function test_basketball_takes_over_when_football_gives_nothing(): void
    {
        $this->configureKeys();
        $this->football = false;
        $this->nba = [
            $this->nbaEvent('nba-1', 'Boston Celtics', 'Washington Wizards', [1.27, 1.28, 1.27], [4.3, 4.2, 4.4]),
            $this->nbaEvent('nba-2', 'Denver Nuggets', 'Utah Jazz', [1.27, 1.28, 1.27], [4.3, 4.2, 4.4]),
            $this->nbaEvent('nba-3', 'Oklahoma City Thunder', 'Charlotte Hornets', [1.27, 1.28, 1.27], [4.3, 4.2, 4.4]),
        ];
        $this->fakeServices();

        $this->artisan('binho:generate-pronostics', ['--level' => ['Bronze']])->assertSuccessful();

        $rules = PronosticRules::for('Bronze');
        $pronostic = Pronostic::query()->sole();
        $this->assertGreaterThanOrEqual(2, count($pronostic->evenements));
        $this->assertGreaterThanOrEqual($rules['min_odds'], (float) $pronostic->cote_totale);

        foreach ($pronostic->evenements as $event) {
            $this->assertSame('basketball', $event['sport']);
            $this->assertSame('basketball_nba', $event['odds_key']);
            $this->assertStringStartsWith('nba-', $event['event_id']);
            $this->assertSame('consensus', $event['categorie']);
        }

        $this->assertStringContainsString('Basketball · NBA', $pronostic->analysis);
        $this->assertStringContainsString('seules les cotes sont disponibles', $pronostic->analysis);
    }

    public function test_each_group_follows_its_own_rules_and_free_and_vip_never_share_a_match(): void
    {
        AppSetting::setValue('openrouter_api_key', 'sk-or-test');
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => '{"coupon": {"selections": []}}']]]])]);
        // L'échelle BIG BOSS consomme 5+4+3+2 = 14 sélections pour les 4 niveaux VIP (voir CLAUDE.md) :
        // le pool doit donc fournir une 15e sélection, la plus faible, pour que Starter (1 événement)
        // ait encore un candidat disponible une fois les VIP servis en premier.
        $this->fakeCandidates([...$this->candidatePool(), $this->candidate(15, 'under', 1.20, 0.76)]);

        $drafts = collect(app(AiPronosticGenerator::class)->generate([], PronosticRules::LEVELS))->keyBy('niveau');

        $this->assertSame(PronosticRules::LEVELS, $drafts->keys()->all());
        $freeMatches = [];
        $vipMatches = [];

        foreach ($drafts as $level => $draft) {
            $rules = PronosticRules::for($level);
            $events = collect($draft['evenements']);

            // Échelle BIG BOSS : le nombre exact d'événements, jamais moins (1, 2, 3, 4, 5).
            $this->assertSame($rules['max_selections'], $events->count(), $level);
            $this->assertGreaterThanOrEqual($rules['min_odds'], $draft['cote_totale'], $level);
            $this->assertLessThanOrEqual($rules['max_odds'], $draft['cote_totale'], $level);
            $this->assertTrue($events->every(fn ($event) => $event['probabilite'] >= $rules['min_probability'] * 100), $level);
            $this->assertFalse($events->contains('selection_key', 'draw'), $level);
            $this->assertLessThanOrEqual(1, $events->whereIn('selection_key', ['home', 'away'])->count(), $level);
            // Victoire sèche : seulement le favori très net (C9 : 85 %, marché 83 %).
            $this->assertNotContains('Équipe 7 vs Adversaire 7', $events->pluck('match')->all(), $level);
            $this->assertStringStartsWith('BIG BOSS — ' . ($rules['free'] ? '' : 'VIP ') . strtoupper($level), $draft['analysis']);

            $rules['free']
                ? array_push($freeMatches, ...$events->pluck('match'))
                : array_push($vipMatches, ...$events->pluck('match'));
        }

        // Un pronostic gratuit n'est jamais revendu comme VIP (et inversement).
        $this->assertSame([], array_values(array_intersect($freeMatches, $vipMatches)));
    }

    public function test_a_one_selection_ai_coupon_is_completed_to_the_ladder_count_keeping_the_ai_pick(): void
    {
        AppSetting::setValue('openrouter_api_key', 'sk-or-test');
        // L'IA ne propose qu'une sélection (C3) : c'est ce qui produisait les coupons VIP à 1,28.
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
            'coupon' => ['selections' => [['id' => 'C3', 'confiance' => 'Moyenne']]],
        ])]]]])]);
        $this->fakeCandidates($this->candidatePool());

        $platinum = app(AiPronosticGenerator::class)->generate([], ['Platinum'])[0];
        $rules = PronosticRules::for('Platinum');

        $this->assertCount(5, $platinum['evenements']);
        $this->assertContains('Équipe 3 vs Adversaire 3', array_column($platinum['evenements'], 'match'));
        $this->assertGreaterThanOrEqual($rules['min_odds'], $platinum['cote_totale']);
        $this->assertLessThanOrEqual($rules['max_odds'], $platinum['cote_totale']);
        $this->assertCount(5, array_unique(array_column($platinum['evenements'], 'match')));
    }

    public function test_the_ladder_count_holds_even_when_a_single_safe_pick_would_be_more_probable(): void
    {
        AppSetting::setValue('openrouter_api_key', 'sk-or-test');
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => '{"coupon": {"selections": []}}']]]])]);
        // Plusieurs sélections par match en tête du pool (le cas réel : 1X, 12, plus de 1,5 but…) :
        // sans limite par match, les premières sélections ne couvraient pas assez de matchs différents.
        $pool = [];
        foreach (range(1, 6) as $n) {
            $pool[] = $this->candidate($n, 'home_draw', 1.30, 0.86);
            $pool[] = [...$this->candidate($n, 'over', 1.32, 0.85), 'id' => "C{$n}B", 'pronostic' => "Plus de 1,5 but {$n}"];
            $pool[] = [...$this->candidate($n, 'home_away', 1.28, 0.84), 'id' => "C{$n}C", 'pronostic' => "Double chance 12 {$n}"];
        }
        $this->fakeCandidates($pool);

        $drafts = collect(app(AiPronosticGenerator::class)->generate([], ['Silver', 'Gold', 'Platinum']))->keyBy('niveau');

        foreach (['Silver' => 3, 'Gold' => 4, 'Platinum' => 5] as $level => $count) {
            $events = $drafts[$level]['evenements'];
            $this->assertCount($count, $events, $level);
            $this->assertCount($count, array_unique(array_column($events, 'match')), $level);
            $this->assertGreaterThan(1.5, $drafts[$level]['cote_totale'], $level);
        }
    }

    public function test_vip_below_eighty_percent_is_published_with_an_adjusted_threshold_note(): void
    {
        AppSetting::setValue('openrouter_api_key', 'sk-or-test');
        Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => '{"coupon": {"selections": []}}']]]])]);
        // Seulement des sélections entre 76 et 79 %.
        $this->fakeCandidates(array_slice($this->candidatePool(), 9));
        $generator = app(AiPronosticGenerator::class);

        $this->assertSame('Bronze', $generator->generate([], ['Bronze'])[0]['niveau']);

        // Rien à 80 % : un coupon sort quand même au palier suivant, et le dit.
        $adjusted = $generator->generate([], ['Silver'])[0];
        $this->assertSame('Silver', $adjusted['niveau']);
        $this->assertSame(1, $adjusted['palier']);
        $this->assertStringContainsString('Fiabilité ajustée : aucun pari à 80 % aujourd’hui, seuil abaissé à 75 %.', $adjusted['analysis']);

        AppSetting::setValue('ai_min_probability_vip', '75');

        $silver = $generator->generate([], ['Silver'])[0];
        $this->assertSame('Silver', $silver['niveau']);
        $rules = PronosticRules::for('Silver');
        $this->assertGreaterThanOrEqual($rules['min_odds'], (float) $silver['cote_totale']);
        $this->assertLessThanOrEqual($rules['max_odds'], (float) $silver['cote_totale']);
    }

    public function test_a_match_given_today_is_not_reused_by_the_other_category_or_the_same_group(): void
    {
        $this->configureKeys();
        $this->fakeServices(['coupon' => ['selections' => []], 'analyses' => [], 'ecartees' => []]);
        $this->fakeCandidates($this->candidatePool());
        $earlier = fn (string $level, int $n) => Pronostic::create([
            'niveau' => $level, 'couleur' => '#000000', 'cote_totale' => 2.1, 'probabilite' => 70,
            'evenements' => [['match' => "Équipe {$n} vs Adversaire {$n}", 'pronostic' => 'Pari', 'cote' => 1.45]],
            'match_date' => now()->toDateString(), 'match_time' => '18:00', 'team_home' => 'A', 'team_away' => 'B',
            'competition' => 'Test', 'prediction' => 'Test', 'odds' => 2.1, 'confidence' => 70, 'analysis' => 'Test',
            'is_vip' => $level !== 'Bronze', 'published_at' => now()->subHour(),
        ]);
        $earlier('Gold', 1);
        $earlier('Bronze', 2);

        $this->artisan('binho:generate-pronostics', ['--level' => ['Bronze']])->assertSuccessful();
        $bronze = Pronostic::query()->where('niveau', 'Bronze')->latest('id')->first();
        $bronzeMatches = array_column($bronze->evenements, 'match');

        $this->assertNotContains('Équipe 1 vs Adversaire 1', $bronzeMatches);
        $this->assertNotContains('Équipe 2 vs Adversaire 2', $bronzeMatches);

        $this->artisan('binho:generate-pronostics', ['--level' => ['Silver']])->assertSuccessful();
        $silver = Pronostic::query()->where('niveau', 'Silver')->sole();

        $this->assertTrue($silver->is_vip);
        $this->assertSame([], array_values(array_intersect([...$bronzeMatches, 'Équipe 2 vs Adversaire 2'], array_column($silver->evenements, 'match'))));
    }

    public function test_the_slot_runner_starts_each_group_once_at_its_time(): void
    {
        $this->configureKeys();
        $this->football = false;
        $this->fakeServices();
        // Heures du Bénin, même si le serveur (et ce test) tournent en UTC.
        // Échelle BIG BOSS (voir CLAUDE.md) : Bronze à 11:00, un seul créneau par niveau VIP.
        $this->travelTo(Carbon::parse('today 11:05', 'Africa/Porto-Novo'));

        // 11:05 : seul le Bronze de 11:00 est dû (Starter 09:00/19:00 dépasse les 20 min de rattrapage).
        $this->artisan('binho:run-pronostic-slots')->expectsOutputToContain('Créneau 11:00 : Bronze')->assertSuccessful();
        $this->artisan('binho:run-pronostic-slots')->expectsOutputToContain('Aucun créneau')->assertSuccessful();

        $run = PronosticRun::query()->sole();
        $this->assertSame(['Bronze', '11:00', PronosticRun::STATUS_EMPTY], [$run->level, $run->slot, $run->status]);

        // Les heures se règlent dans Admin > Paramètres.
        AppSetting::setValue('ai_schedule_gold', '09:00, 11:10');
        $this->travelTo(Carbon::parse('today 11:11', 'Africa/Porto-Novo'));
        $this->artisan('binho:run-pronostic-slots')->expectsOutputToContain('Créneau 11:10 : Gold')->assertSuccessful();

        $this->assertSame(['Bronze', 'Gold'], PronosticRun::query()->orderBy('id')->pluck('level')->all());
        $this->assertSame(['09:00', '11:10'], PronosticRules::schedule()['Gold']);
    }

    public function test_the_admin_can_only_save_valid_publication_times(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/settings', ['ai_schedule_silver' => 'le matin', 'ai_min_probability_vip' => '40'])
            ->assertSessionHasErrors(['ai_schedule_silver', 'ai_min_probability_vip']);

        // Échelle BIG BOSS (voir CLAUDE.md) : un seul créneau par niveau VIP, Silver = 13:00.
        $this->assertSame(['13:00'], PronosticRules::schedule()['Silver']);
    }

    public function test_an_exhausted_odds_quota_is_reported_to_the_admin(): void
    {
        $this->configureKeys();
        Http::fake([
            'api.the-odds-api.com/*' => Http::response(['message' => 'Usage quota has been reached'], 401, ['x-requests-remaining' => '0', 'x-requests-used' => '500']),
            'soccer.highlightly.net/*' => fn ($request) => $this->highlightly($request),
            '*' => Http::response(['data' => []]),
        ]);

        $this->artisan('binho:generate-pronostics', ['--level' => ['Bronze']])
            ->expectsOutputToContain('Quota The Odds API épuisé')
            ->assertSuccessful();

        $this->assertSame(0, \App\Services\OddsService::usage()['remaining']);
        $this->assertStringContainsString('Quota The Odds API épuisé', PronosticRun::query()->sole()->message);
        $this->actingAs($this->admin())
            ->get('/admin/pronostics')
            ->assertOk()
            ->assertSee('Créneaux du jour', false)
            ->assertSee('Quota The Odds API épuisé', false);
    }

    public function test_the_silver_coupon_is_for_vip_silver_subscribers_only(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);
        $command = app(GenerateDailyPronostics::class);
        $publish = (new \ReflectionClass($command))->getMethod('publish');

        $silver = $publish->invoke($command, [
            'niveau' => 'Silver', 'cote_totale' => 2.2, 'probabilite' => 64, 'analysis' => 'Test',
            'evenements' => [['match' => 'Arsenal vs Chelsea', 'pronostic' => 'Double chance 1X (Arsenal ou nul)', 'cote' => 1.45, 'kickoff_at' => now()->addHours(3)->toIso8601String()]],
        ]);
        $starter = $publish->invoke($command, [
            'niveau' => 'Starter', 'cote_totale' => 2.1, 'probabilite' => 60, 'analysis' => 'Test',
            'evenements' => [['match' => 'PSG vs Lyon', 'pronostic' => 'Double chance 12 (PSG ou Lyon)', 'cote' => 1.4]],
        ]);

        $this->assertTrue($silver->is_vip);
        $this->assertSame('VIP Silver', $silver->competition);
        // Un seul niveau gratuit désormais : Starter. Bronze a rejoint les niveaux VIP.
        $this->assertFalse($starter->is_vip);

        $player = User::factory()->create(['is_active' => true]);
        \Laravel\Sanctum\Sanctum::actingAs($player);
        $locked = collect($this->getJson('/api/v1/pronostics')->assertOk()->json('pronostics'))->firstWhere('niveau', 'Silver');
        $this->assertTrue($locked['locked']);
    }

    public function test_the_admin_instructions_follow_the_big_boss_document(): void
    {
        $instructions = AppSetting::value('ai_pronostic_instructions');

        $this->assertStringStartsWith('BIG BOSS — INSTRUCTIONS PRINCIPALES', $instructions);
        // Bronze est VIP (voir CLAUDE.md) : seul Starter reste au seuil 75 %.
        $this->assertStringContainsString('75 % par sélection pour Starter (seul niveau gratuit) et 80 % pour les VIP', $instructions);
        $this->assertStringContainsString('corners, cartons', $instructions);
        $this->assertStringContainsString('BIG BOSS — [NOM DU GROUPE]', $instructions);
        $this->assertStringContainsString('ne publie rien', $instructions);
        // Échelle progressive BIG BOSS (voir CLAUDE.md) : une sélection de plus par niveau VIP.
        $this->assertStringContainsString('une sélection de plus que le précédent', $instructions);
        // Les prix restent ceux de Admin > VIP, jamais dans les consignes de l'IA.
        $this->assertStringNotContainsString('FCFA', $instructions);
    }

    /**
     * Un bookmaker par cote fournie : ['home' => [1.58, 1.60]] = deux bookmakers.
     */
    private function oddsEvent(string $home, string $away, array $h2h, array $totals): array
    {
        $count = max(count($h2h['home']), count($totals['over']));
        $bookmakers = [];

        for ($i = 0; $i < $count; $i++) {
            $markets = [];

            if (isset($h2h['home'][$i])) {
                $markets[] = ['key' => 'h2h', 'outcomes' => [
                    ['name' => $home, 'price' => $h2h['home'][$i]],
                    ['name' => 'Draw', 'price' => $h2h['draw'][$i]],
                    ['name' => $away, 'price' => $h2h['away'][$i]],
                ]];
            }

            if (isset($totals['over'][$i])) {
                $markets[] = ['key' => 'totals', 'outcomes' => [
                    ['name' => 'Over', 'price' => $totals['over'][$i], 'point' => 2.5],
                    ['name' => 'Under', 'price' => $totals['under'][$i], 'point' => 2.5],
                ]];
            }

            $bookmakers[] = ['key' => "book{$i}", 'title' => "Book {$i}", 'markets' => $markets];
        }

        return [
            'id' => md5($home . $away),
            'commence_time' => now()->addHours(3)->toIso8601String(),
            'home_team' => $home,
            'away_team' => $away,
            'bookmakers' => $bookmakers,
        ];
    }
}
