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

    // Un seul niveau gratuit : Starter. Bronze, Silver, Gold et Platinum sont VIP
    // (voir aussi AppSetting::VIP_LEVELS, qui doit rester synchronisé avec cette liste).
    public const FREE_LEVELS = ['Starter'];

    // Les heures de publication sont celles du Bénin, quel que soit le fuseau du serveur.
    public const TIMEZONE = 'Africa/Porto-Novo';

    // Cote d'une sélection : jamais sous 1,15.
    public const MIN_SELECTION_ODDS = 1.15;

    // Mêmes règles que Binho Flash (combinés fiables, à cote modérée) : un pari à 75/80 % est
    // un favori à petite cote (1,15–1,30), et quelques-uns suffisent rarement à atteindre 2,00.
    // Garder un plancher à 2,00 revenait à ne (presque) jamais publier. On publie donc des
    // combinés fiables mais à cote modérée (le combiné affiche toujours sa vraie probabilité).
    // La fiabilité par pari (75/80 %) reste intacte ; c'est le plancher de cote qui a été abaissé.
    private const LIMITS = [
        // max sélections, cote totale minimale, cote totale maximale
        'Starter' => [3, 1.50, 3.00],
        'Bronze' => [3, 1.60, 3.50],
        'Silver' => [3, 1.60, 3.50],
        'Gold' => [3, 1.70, 4.50],
        // Platinum peut aller jusqu'à 4 sélections sans sacrifier la fiabilité.
        'Platinum' => [4, 1.85, 5.50],
    ];

    private const DEFAULT_SCHEDULE = [
        'Starter' => '09:00',
        'Bronze' => '16:30',
        'Silver' => '10:00, 15:00, 20:00',
        'Gold' => '09:00, 13:00, 18:00, 23:30',
        'Platinum' => '08:00, 12:00, 16:00, 21:00',
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
