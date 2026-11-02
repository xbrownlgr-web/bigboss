<?php

namespace App\Support;

use App\Models\AppSetting;

/**
 * Règles de chaque groupe (consignes principales) : nombre de sélections, cote totale,
 * probabilité minimale par sélection et heures de publication (réglables dans l'admin).
 */
class PronosticRules
{
    public const LEVELS = ['Starter', 'Bronze', 'Silver', 'Gold', 'Platinum'];

    public const FREE_LEVELS = ['Starter', 'Bronze'];

    // Les heures de publication sont celles du Bénin, quel que soit le fuseau du serveur.
    public const TIMEZONE = 'Africa/Porto-Novo';

    // Cote d'une sélection : jamais sous 1,15.
    public const MIN_SELECTION_ODDS = 1.15;

    // Échelle BIG BOSS : chaque niveau ajoute un événement et monte en cote.
    // Niveau 1 = 1 événement, cote ~1,20 (favori très net), publié matin ET soir.
    // Niveau 2 = 2 événements, … jusqu'au dernier (5 événements). La fiabilité par
    // sélection (seuil de probabilité) reste appliquée ; seules la taille du combiné
    // et la cote changent d'un niveau à l'autre.
    private const LIMITS = [
        // max sélections, cote totale minimale, cote totale maximale
        'Starter' => [1, 1.15, 1.40],   // Niveau 1 : 1 événement, ~1,20
        'Bronze' => [2, 1.45, 2.10],    // Niveau 2 : 2 événements
        'Silver' => [3, 1.90, 3.20],    // Niveau 3 : 3 événements
        'Gold' => [4, 2.60, 4.80],      // Niveau 4 : 4 événements
        'Platinum' => [5, 3.40, 7.00],  // Niveau 5 : 5 événements
    ];

    private const DEFAULT_SCHEDULE = [
        // Niveau 1 : matin ET soir. Les autres : une fois par jour.
        'Starter' => '09:00, 19:00',
        'Bronze' => '11:00',
        'Silver' => '13:00',
        'Gold' => '16:00',
        'Platinum' => '20:00',
    ];

    public static function isFree(string $level): bool
    {
        return in_array($level, self::FREE_LEVELS, true);
    }

    /**
     * @return array{level: string, max_selections: int, min_odds: float, max_odds: float, min_probability: float, free: bool}
     */
    public static function for(string $level): array
    {
        [$max, $minOdds, $maxOdds] = self::LIMITS[$level] ?? self::LIMITS['Bronze'];
        $free = self::isFree($level);
        $percent = (float) (AppSetting::value($free ? 'ai_min_probability_free' : 'ai_min_probability_vip') ?: ($free ? 75 : 80));

        return [
            'level' => $level,
            'max_selections' => $max,
            'min_odds' => $minOdds,
            'max_odds' => $maxOdds,
            'min_probability' => max(0.5, min(0.99, $percent / 100)),
            'free' => $free,
        ];
    }

    /**
     * Heures de publication de chaque niveau, « HH:MM » triées (heure du Bénin).
     *
     * @return array<string, array<int, string>>
     */
    public static function schedule(): array
    {
        $schedule = [];

        foreach (self::LEVELS as $level) {
            $raw = AppSetting::value('ai_schedule_' . strtolower($level)) ?? self::DEFAULT_SCHEDULE[$level];
            preg_match_all('/\b([01]?\d|2[0-3])[:hH]([0-5]\d)\b/', (string) $raw, $matches, PREG_SET_ORDER);

            $times = collect($matches)
                ->map(fn ($match) => sprintf('%02d:%02d', (int) $match[1], (int) $match[2]))
                ->unique()
                ->sort()
                ->values()
                ->all();

            $schedule[$level] = $times;
        }

        return $schedule;
    }
}
