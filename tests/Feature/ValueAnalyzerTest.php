<?php

namespace Tests\Feature;

use App\Services\ValueAnalyzer;
use Tests\TestCase;

class ValueAnalyzerTest extends TestCase
{
    private function fixture(array $selection, ?array $data = null): array
    {
        return [
            'id' => 1,
            'match' => 'Arsenal vs Chelsea',
            'home' => 'Arsenal',
            'away' => 'Chelsea',
            'selections' => [$selection],
            'data' => $data ?? [
                'type' => 'championnat',
                'standings' => [
                    'home' => $this->row(['games' => 8, 'scored' => 20, 'received' => 5]),
                    'away' => $this->row(['games' => 8, 'scored' => 6, 'received' => 15]),
                ],
                'form' => [
                    'home' => array_fill(0, 5, ['gf' => 2, 'ga' => 0]),
                    'away' => array_fill(0, 5, ['gf' => 0, 'ga' => 2]),
                ],
                'details' => ['predictions' => ['home' => 0.70, 'draw' => 0.18, 'away' => 0.12]],
            ],
        ];
    }

    private function row(array $split): array
    {
        $split += ['wins' => 0, 'draws' => 0, 'loses' => 0];

        return ['position' => 1, 'points' => 20, 'total' => $split, 'home' => $split, 'away' => $split];
    }

    private function evaluate(array $selection, ?array $data = null): array
    {
        return (new ValueAnalyzer())->analyseFixture($this->fixture($selection, $data))['selections'][0];
    }

    public function test_poisson_probabilities_add_up_to_one(): void
    {
        $table = (new ValueAnalyzer())->poissonTable(1.6, 1.1);

        $this->assertEqualsWithDelta(1.0, $table['home'] + $table['draw'] + $table['away'], 0.0001);
        $this->assertGreaterThan($table['away'], $table['home']);
    }

    public function test_a_selection_with_value_is_kept(): void
    {
        $result = $this->evaluate(['key' => 'home', 'label' => 'Victoire Arsenal', 'cote' => 1.70, 'fair' => 0.577, 'bookmakers' => 3]);

        $this->assertTrue($result['retenue'], (string) $result['rejet']);
        $this->assertGreaterThan(0, $result['valeur']);
        // Plus de 5 points d'avance et les deux sources indépendantes d'accord, en championnat.
        $this->assertSame('Élevée', $result['confiance']);
    }

    public function test_cups_never_get_high_confidence(): void
    {
        $data = $this->fixture([])['data'];
        $data['type'] = 'coupe';

        $result = $this->evaluate(['key' => 'home', 'label' => 'Victoire Arsenal', 'cote' => 1.70, 'fair' => 0.577, 'bookmakers' => 3], $data);

        $this->assertTrue($result['retenue']);
        $this->assertSame('Moyenne', $result['confiance']);
    }

    public function test_odds_below_1_30_are_refused(): void
    {
        $result = $this->evaluate(['key' => 'home', 'label' => 'Victoire Arsenal', 'cote' => 1.25, 'fair' => 0.75, 'bookmakers' => 3]);

        $this->assertFalse($result['retenue']);
        $this->assertSame('cote inférieure à 1,30', $result['rejet']);
    }

    public function test_no_bet_without_data_independent_from_the_market(): void
    {
        $result = $this->evaluate(
            ['key' => 'home', 'label' => 'Victoire Arsenal', 'cote' => 1.70, 'fair' => 0.62, 'bookmakers' => 3],
            ['type' => 'championnat', 'standings' => null, 'form' => ['home' => null, 'away' => null], 'details' => null],
        );

        $this->assertFalse($result['retenue']);
        $this->assertStringContainsString('aucune donnée indépendante', $result['rejet']);
    }

    public function test_a_huge_gap_with_the_market_means_something_is_missing(): void
    {
        $result = $this->evaluate(['key' => 'home', 'label' => 'Victoire Arsenal', 'cote' => 3.20, 'fair' => 0.30, 'bookmakers' => 3]);

        $this->assertFalse($result['retenue']);
        $this->assertStringContainsString('écart énorme', $result['rejet']);
    }

    public function test_fewer_than_five_games_is_low_reliability_and_not_used(): void
    {
        $expected = (new ValueAnalyzer())->expectedGoals([
            'standings' => null,
            'form' => [
                'home' => array_fill(0, 4, ['gf' => 2, 'ga' => 0]),
                'away' => array_fill(0, 4, ['gf' => 0, 'ga' => 2]),
            ],
        ]);

        $this->assertSame('faible', $expected['fiabilite']);
    }

    public function test_a_sharp_odds_drop_is_reported_as_a_risk(): void
    {
        $result = $this->evaluate(['key' => 'home', 'label' => 'Victoire Arsenal', 'cote' => 1.70, 'fair' => 0.577, 'bookmakers' => 3, 'first_cote' => 2.10]);

        $this->assertNotEmpty(array_filter($result['risques'], fn ($risk) => str_contains($risk, 'forte baisse')));
    }
}
