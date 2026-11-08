<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Services\OddsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Offre gratuite The Odds API : 4 championnats relevés (payants) par génération, mais tous ceux
 * déjà relevés dans la journée et encore frais servent gratuitement (créneau du soir trop pauvre
 * le 2026-11-08 : Platinum à 1 sélection).
 */
class OddsCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_leagues_already_fetched_today_are_used_without_spending_credits(): void
    {
        AppSetting::setValue('odds_api_key', 'odds-test');
        $kickoff = now()->addHours(3)->startOfHour();

        $matches = [];
        foreach (range(1, 6) as $n) {
            $matches[] = ['id' => $n, 'odds_key' => "soccer_test_{$n}", 'priority' => 2, 'match' => "Domicile {$n} vs Extérieur {$n}",
                'home' => "Domicile {$n}", 'away' => "Extérieur {$n}", 'kickoff_at' => $kickoff->toIso8601String(), 'league' => "Ligue {$n}"];
        }

        // Le 6e championnat (hors des 4 relevés payants) a été relevé il y a 2 heures.
        Cache::put('odds:sport:soccer_test_6:h2h,totals', ['at' => now()->subHours(2)->toIso8601String(), 'events' => [[
            'id' => 'ev6', 'commence_time' => $kickoff->toIso8601String(), 'home_team' => 'Domicile 6', 'away_team' => 'Extérieur 6',
            'bookmakers' => [['key' => 'book', 'markets' => [
                ['key' => 'h2h', 'outcomes' => [['name' => 'Domicile 6', 'price' => 1.60], ['name' => 'Extérieur 6', 'price' => 5.50], ['name' => 'Draw', 'price' => 4.00]]],
                ['key' => 'totals', 'outcomes' => [['name' => 'Over', 'price' => 1.80, 'point' => 2.5], ['name' => 'Under', 'price' => 2.00, 'point' => 2.5]]],
            ]]],
        ]]], now()->addHours(24));

        Http::fake(['api.the-odds-api.com/*' => Http::response([], 200, ['x-requests-remaining' => '300', 'x-requests-used' => '200', 'x-requests-last' => '2'])]);

        $attached = collect(app(OddsService::class)->attach($matches))->keyBy('id');

        $this->assertNotEmpty($attached[6]['selections'], 'le championnat déjà relevé doit fournir ses cotes');
        Http::assertSentCount(OddsService::MAX_SPORTS);
    }
}
