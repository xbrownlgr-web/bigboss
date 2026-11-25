<?php

namespace Tests\Feature;

use App\Services\AiPronosticGenerator;
use App\Services\SpecialMarketsGenerator;
use App\Services\ValueAnalyzer;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PronosticDiversityAndSpecialMarketsTest extends TestCase
{
    public function test_grosses_cotes_populates_explicit_pronostic_and_line_from_odds_service_shape(): void
    {
        $generator = new SpecialMarketsGenerator(new ValueAnalyzer);

        // Forme exacte produite par OddsService::extractSelections() ('label' et 'point', pas 'pronostic' ni 'line').
        $fixtures = [];
        $teams = [
            ['Paris Saint Germain', 'Lyon', 'Ligue 1'],
            ['Arsenal', 'Chelsea', 'Premier League'],
            ['Real Madrid', 'Sevilla', 'La Liga'],
            ['Inter Milan', 'Roma', 'Serie A'],
            ['Bayern Munich', 'Leipzig', 'Bundesliga'],
            ['Slavia Praha', 'Villarreal', 'Champions League'],
            ['Benfica', 'Porto', 'Primeira Liga'],
            ['Ajax', 'Feyenoord', 'Eredivisie'],
        ];

        foreach ($teams as $i => [$home, $away, $comp]) {
            $fixtures[] = [
                'id' => 1000 + $i,
                'event_id' => 'evt_' . $i,
                'sport' => 'football',
                'competition' => $comp,
                'match' => "{$home} vs {$away}",
                'home' => $home,
                'away' => $away,
                'kickoff' => '20:00',
                'kickoff_at' => now()->addHours(4)->toIso8601String(),
                'data' => [],
                'selections' => [
                    ['key' => 'home', 'label' => "Victoire {$home}", 'cote' => 1.65],
                    ['key' => 'home_away', 'label' => "Double chance 12 ({$home} ou {$away})", 'cote' => 1.32],
                    ['key' => 'over', 'label' => 'Plus de 2,5 buts', 'cote' => 1.75, 'point' => 2.5],
                ],
            ];
        }

        $dossier = $generator->buildDossier($fixtures, Carbon::now());
        $firstSelection = $dossier['matchs'][0]['selections_reelles_1_25_a_2_10'][0];
        $this->assertSame('Victoire Paris Saint Germain', $firstSelection['pronostic']);
        $overSelection = $dossier['matchs'][0]['selections_reelles_1_25_a_2_10'][2];
        $this->assertSame('Plus de 2,5 buts', $overSelection['pronostic']);
        $this->assertSame(2.5, $overSelection['line']);

        $combines = $generator->buildServerCombines($fixtures);
        $this->assertNotEmpty($combines, 'Au moins un combiné Grosses cotes doit être construit.');

        foreach ($combines as $combine) {
            foreach ($combine['selections'] as $leg) {
                $this->assertNotSame('', trim((string) $leg['pronostic']), 'Chaque événement Grosse cote doit préciser le pari et l’équipe.');
                $this->assertNotSame('', trim((string) $leg['match']));
                $this->assertNotSame('', trim((string) $leg['marche']));
            }
        }
    }

    public function test_cartons_and_buteurs_server_builders_produce_valid_picks(): void
    {
        $generator = new SpecialMarketsGenerator(new ValueAnalyzer);

        $fixtures = [
            [
                'id' => 2001,
                'competition' => 'Champions League',
                'match' => 'Arsenal vs Bayern Munich',
                'home' => 'Arsenal',
                'away' => 'Bayern Munich',
                'kickoff' => '21:00',
                'kickoff_at' => now()->addHours(3)->toIso8601String(),
                'data' => [
                    'standings' => [
                        'teams' => 20,
                        'home' => ['position' => 2, 'points' => 52, 'total' => ['games' => 22, 'scored' => 48, 'received' => 18]],
                        'away' => ['position' => 1, 'points' => 55, 'total' => ['games' => 22, 'scored' => 54, 'received' => 20]],
                    ],
                ],
                'selections' => [
                    ['key' => 'home', 'label' => 'Victoire Arsenal', 'cote' => 2.25],
                    ['key' => 'away', 'label' => 'Victoire Bayern Munich', 'cote' => 2.95],
                ],
                'scorers' => [
                    ['player' => 'Harry Kane', 'team' => 'Bayern Munich', 'team_id' => 5, 'goals' => 19, 'penalties' => 5, 'played_matches' => 20, 'side' => 'away'],
                    ['player' => 'Bukayo Saka', 'team' => 'Arsenal', 'team_id' => 57, 'goals' => 11, 'penalties' => 3, 'played_matches' => 21, 'side' => 'home'],
                ],
            ],
            [
                'id' => 2002,
                'competition' => 'Ligue 1',
                'match' => 'Marseille vs Lyon',
                'home' => 'Marseille',
                'away' => 'Lyon',
                'kickoff' => '20:45',
                'kickoff_at' => now()->addHours(4)->toIso8601String(),
                'data' => [
                    'standings' => [
                        'teams' => 18,
                        'home' => ['position' => 3, 'points' => 42, 'total' => ['games' => 21, 'scored' => 40, 'received' => 24]],
                        'away' => ['position' => 4, 'points' => 40, 'total' => ['games' => 21, 'scored' => 37, 'received' => 25]],
                    ],
                ],
                'selections' => [
                    ['key' => 'home', 'label' => 'Victoire Marseille', 'cote' => 2.15],
                    ['key' => 'away', 'label' => 'Victoire Lyon', 'cote' => 3.10],
                ],
                'scorers' => [
                    ['player' => 'Mason Greenwood', 'team' => 'Marseille', 'team_id' => 516, 'goals' => 14, 'penalties' => 4, 'played_matches' => 20, 'side' => 'home'],
                ],
            ],
        ];

        $cartons = $generator->buildServerCartons($fixtures, [], 3);
        $this->assertCount(2, $cartons);
        foreach ($cartons as $carton) {
            $this->assertGreaterThanOrEqual(SpecialMarketsGenerator::CARTON_MIN_ODD, $carton['cote']);
            $this->assertLessThanOrEqual(SpecialMarketsGenerator::CARTON_MAX_ODD, $carton['cote']);
            $this->assertGreaterThan(0, $carton['valeur']);
            $this->assertStringContainsString('carton', mb_strtolower($carton['pronostic']));
        }

        $buteurs = $generator->buildServerButeurs($fixtures, [], 3);
        $this->assertCount(2, $buteurs);
        foreach ($buteurs as $buteur) {
            $this->assertGreaterThanOrEqual(SpecialMarketsGenerator::BUTEUR_MIN_ODD, $buteur['cote']);
            $this->assertGreaterThan(0, $buteur['valeur']);
            $this->assertFalse($buteur['composition_confirmee']);
            $this->assertNotSame('elevee', $buteur['confiance']);
            $this->assertContains($buteur['joueur'], ['Harry Kane', 'Bukayo Saka', 'Mason Greenwood']);
        }
    }

    public function test_selection_signature_blocks_identical_bet_across_levels(): void
    {
        $sigHome = AiPronosticGenerator::selectionSignature('Slavia Praha vs Villarreal', 'home', null);
        $sigDc = AiPronosticGenerator::selectionSignature('Slavia Praha vs Villarreal', 'home_away', null);
        $sigOver = AiPronosticGenerator::selectionSignature('Slavia Praha vs Villarreal', 'over', 2.5);

        $this->assertSame('slavia praha vs villarreal|home', $sigHome);
        $this->assertNotSame($sigHome, $sigDc);
        $this->assertSame('slavia praha vs villarreal|over:2.5', $sigOver);
    }
}
