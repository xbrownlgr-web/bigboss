<?php

namespace Tests\Feature;

use App\Models\Pronostic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PronosticHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function pronostic(array $attributes = []): Pronostic
    {
        return Pronostic::create(array_merge([
            'niveau' => 'Bronze',
            'couleur' => '#92400E',
            'cote_totale' => 2.1,
            'probabilite' => 72,
            'evenements' => [['match' => 'Arsenal vs Chelsea', 'pronostic' => 'Double chance 1X', 'cote' => 1.45]],
            'match_date' => now()->toDateString(),
            'match_time' => '18:00',
            'team_home' => 'Arsenal',
            'team_away' => 'Chelsea',
            'competition' => 'Combiné Bronze',
            'prediction' => 'Combiné 1 événement',
            'odds' => 2.1,
            'confidence' => 72,
            'analysis' => 'Analyse test.',
            'is_vip' => false,
            'published_at' => now(),
        ], $attributes));
    }

    public function test_history_returns_the_win_rate_and_only_settled_coupons(): void
    {
        $this->pronostic(['result_status' => 'won']);
        $this->pronostic(['result_status' => 'won']);
        $this->pronostic(['result_status' => 'lost']);
        // En attente de résultat : exclu du bilan et de la liste.
        $this->pronostic(['result_status' => null]);

        $response = $this->getJson('/api/v1/pronostics/history')->assertSuccessful();

        $response->assertJsonPath('stats.settled', 3);
        $response->assertJsonPath('stats.won', 2);
        $response->assertJsonPath('stats.lost', 1);
        $response->assertJsonPath('stats.win_rate', 67);
        $this->assertCount(3, $response->json('pronostics'));
    }

    public function test_history_hides_vip_matches_for_non_subscribers_but_keeps_result_and_odds(): void
    {
        $this->pronostic([
            'niveau' => 'Silver',
            'competition' => 'VIP Silver',
            'is_vip' => true,
            'cote_totale' => 3.2,
            'result_status' => 'won',
        ]);

        $item = $this->getJson('/api/v1/pronostics/history')->assertSuccessful()->json('pronostics.0');

        $this->assertTrue($item['locked']);
        $this->assertSame([], $item['evenements']);
        $this->assertSame('won', $item['result_status']);
        $this->assertSame('Silver', $item['niveau']);
        $this->assertEquals(3.2, (float) $item['cote_totale']);
    }
}
