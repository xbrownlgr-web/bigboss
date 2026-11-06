<?php

namespace App\Support;

/**
 * Championnats couverts par le plan gratuit (TIER_ONE) de football-data.org, vérifiés en direct sur
 * GET https://api.football-data.org/v4/competitions le 2026-11-06 (champ « plan »). Seuls ces
 * championnats peuvent basculer sur football-data.org pour le classement (économise le quota
 * Highlightly) ; tous les autres restent sur Highlightly, inchangé. Les résultats pour le règlement
 * des combinés (argent réel) ne passent PAS par cette source : les identifiants de match diffèrent
 * d'un fournisseur à l'autre, un rapprochement par nom serait risqué (voir CLAUDE.md).
 */
class FootballDataLeagues
{
    // Clé The Odds API (LeagueCatalog::LEAGUES) => code compétition football-data.org v4.
    public const BY_ODDS_KEY = [
        'soccer_epl' => 'PL',
        'soccer_spain_la_liga' => 'PD',
        'soccer_germany_bundesliga' => 'BL1',
        'soccer_france_ligue_one' => 'FL1',
        'soccer_netherlands_eredivisie' => 'DED',
        'soccer_brazil_campeonato' => 'BSA',
        'soccer_italy_serie_a' => 'SA',
        'soccer_efl_champ' => 'ELC',
        'soccer_portugal_primeira_liga' => 'PPL',
        'soccer_uefa_champs_league' => 'CL',
    ];

    public static function competitionCode(?string $oddsKey): ?string
    {
        return $oddsKey !== null ? (self::BY_ODDS_KEY[$oddsKey] ?? null) : null;
    }
}
