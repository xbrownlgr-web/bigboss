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

    // Échelle BIG BOSS (règle établie avec le fondateur — voir CLAUDE.md) : chaque niveau VIP a
    // UN événement de plus que le précédent. Starter = 1 (entrée gratuite), Bronze = 2, Silver = 3,
    // Gold = 4, Platinum = 5 (le maximum). C'est une règle business (différenciation des offres),
    // indépendante de la fiabilité d'une sélection (MIN_SELECTION_ODDS + seuils de probabilité
    // ai_min_probability_free/vip, eux inspirés de Binho) : ne jamais remplacer cette échelle en
    // copiant une autre config, même si on copie par ailleurs des réglages de fiabilité.
    private const LIMITS = [
        // max sélections, cote totale minimale, cote totale maximale
        'Starter' => [1, 1.15, 1.40],
        'Bronze' => [2, 1.45, 2.10],
        'Silver' => [3, 1.90, 3.20],
        'Gold' => [4, 2.60, 4.80],
        'Platinum' => [5, 3.40, 7.00],
    ];

    // Une seule génération par niveau et par jour (pas plusieurs créneaux qui s'empilent) : le
    // nombre d'événements du jour doit correspondre à l'échelle ci-dessus. Starter a deux créneaux
    // (matin + soir) car il reste à 1 événement à chaque fois.
    private const DEFAULT_SCHEDULE = [
        'Starter' => '09:00, 19:00',
        'Bronze' => '11:00',
        'Silver' => '13:00',
        // Avancés le 2026-11-08 (décision du fondateur) : à 16:00 / 20:00 il ne restait plus assez
        // de matchs dans la journée pour 4 et 5 événements (coupons de secours à 1-2 sélections).
        'Gold' => '12:00',
        'Platinum' => '14:00',
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
            $schedule[$level] = self::times(AppSetting::value('ai_schedule_' . strtolower($level)) ?? self::DEFAULT_SCHEDULE[$level]);
        }

        return $schedule;
    }

    /**
     * « 10:00, 15h30 » → ['10:00', '15:30'] (triées, sans doublon).
     *
     * @return array<int, string>
     */
    public static function times(?string $raw): array
    {
        preg_match_all('/\b([01]?\d|2[0-3])[:hH]([0-5]\d)\b/', (string) $raw, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(fn ($match) => sprintf('%02d:%02d', (int) $match[1], (int) $match[2]))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }
}
