<?php

namespace App\Services;

use App\Models\Pronostic;

/**
 * Détermine l'issue d'un combiné (gagné / perdu) à partir des résultats réels des matchs.
 * Prudence absolue : on ne déclare « gagné » que si TOUTES les sélections sont confirmées gagnantes.
 * La moindre incertitude (match non terminé, marché inconnu) laisse le combiné en attente (null).
 */
class PronosticSettlementService
{
    /**
     * @param array<int, array{home: string, away: string, home_goals: ?int, away_goals: ?int, finished: bool}> $resultsById
     * @return string|null 'won', 'lost', ou null (pas encore décidable)
     */
    public function settle(Pronostic $pronostic, array $resultsById): ?string
    {
        $events = $pronostic->evenements ?? [];

        if ($events === []) {
            return null;
        }

        $allWon = true;

        foreach ($events as $event) {
            $verdict = $this->settleLeg(is_array($event) ? $event : [], $resultsById);

            // Un combiné tombe dès qu'une seule sélection perd, même si les autres ne sont pas finies.
            if ($verdict === 'lost') {
                return 'lost';
            }

            if ($verdict !== 'won') {
                $allWon = false;
            }
        }

        return $allWon ? 'won' : null;
    }

    /**
     * @param array<string, mixed> $event
     * @param array<int, array{home_goals: ?int, away_goals: ?int, finished: bool}> $resultsById
     */
    private function settleLeg(array $event, array $resultsById): ?string
    {
        $key = $event['selection_key'] ?? null;
        $football = ($event['sport'] ?? 'football') === 'football';
        // Football : identifiant Highlightly ; basket / handball : identifiant The Odds API.
        $id = $football ? (isset($event['fixture_id']) ? (int) $event['fixture_id'] : null) : ($event['event_id'] ?? null);

        if ($key === null || $id === null || ! isset($resultsById[$id])) {
            return null;
        }

        $result = $resultsById[$id];

        if (empty($result['finished']) || $result['home_goals'] === null || $result['away_goals'] === null) {
            return null;
        }

        $home = (int) $result['home_goals'];
        $away = (int) $result['away_goals'];
        $line = isset($event['line']) ? (float) $event['line'] : null;

        return match ($key) {
            'home' => $home > $away ? 'won' : 'lost',
            'away' => $away > $home ? 'won' : 'lost',
            'draw' => $home === $away ? 'won' : 'lost',
            'home_draw' => $home >= $away ? 'won' : 'lost',
            'draw_away' => $away >= $home ? 'won' : 'lost',
            'home_away' => $home !== $away ? 'won' : 'lost',
            'btts_yes' => $home > 0 && $away > 0 ? 'won' : 'lost',
            'btts_no' => $home > 0 && $away > 0 ? 'lost' : 'won',
            'spread_home', 'spread_away' => $this->settleSpread($key, $home, $away, $line),
            'over', 'under' => $this->settleTotal($key, $home + $away, $line),
            default => null,
        };
    }

    /**
     * Handicap (basket, handball) : la ligne est ajoutée au score de l'équipe choisie ; égalité
     * exacte (remboursement) laissée à l'admin.
     */
    private function settleSpread(string $key, int $home, int $away, ?float $line): ?string
    {
        if ($line === null) {
            return null;
        }

        $margin = $key === 'spread_home' ? $home + $line - $away : $away + $line - $home;

        if (abs($margin) < 0.001) {
            return null;
        }

        return $margin > 0 ? 'won' : 'lost';
    }

    private function settleTotal(string $key, int $total, ?float $line): ?string
    {
        // On ne règle que les lignes classiques en x.5 : pas d'ambiguïté de remboursement (push).
        if ($line === null || abs(($line - floor($line)) - 0.5) > 0.001) {
            return null;
        }

        $over = $total > $line;

        return $key === 'over'
            ? ($over ? 'won' : 'lost')
            : ($over ? 'lost' : 'won');
    }
}
