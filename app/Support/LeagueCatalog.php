<?php

namespace App\Support;

/**
 * Championnats analysables : un match n'est étudié que si ses cotes réelles existent chez
 * The Odds API. Priorité 1 = ligues prioritaires des consignes, puis 2 et 3 (« d'autres
 * compétitions sont possibles si les données sont fiables »).
 * Reconnaissance par identifiant Highlightly quand il est connu, sinon par pays + nom.
 */
class LeagueCatalog
{
    // Identifiants Highlightly vérifiés (highlightly.net/leagues, 10/10/2026).
    private const BY_ID = [
        33973 => 'soccer_epl',
        52695 => 'soccer_france_ligue_one',
        115669 => 'soccer_italy_serie_a',
        67162 => 'soccer_germany_bundesliga',
        34824 => 'soccer_efl_champ',
        119924 => 'soccer_spain_la_liga',
        75672 => 'soccer_netherlands_eredivisie',
        80778 => 'soccer_portugal_primeira_liga',
        173537 => 'soccer_turkey_super_league',
        123328 => 'soccer_belgium_first_div',
        2486 => 'soccer_uefa_champs_league',
        3337 => 'soccer_uefa_europa_league',
        722432 => 'soccer_uefa_europa_conference_league',
        5039 => 'soccer_uefa_nations_league',
    ];

    // Clé The Odds API => [libellé, priorité, pays (minuscules, sans accents), noms possibles].
    public const LEAGUES = [
        'soccer_epl' => ['Premier League', 1, ['england'], ['premier league']],
        'soccer_spain_la_liga' => ['Liga', 1, ['spain'], ['la liga', 'laliga', 'primera division']],
        'soccer_germany_bundesliga' => ['Bundesliga', 1, ['germany'], ['bundesliga']],
        'soccer_france_ligue_one' => ['Ligue 1', 1, ['france'], ['ligue 1']],
        'soccer_netherlands_eredivisie' => ['Eredivisie', 1, ['netherlands'], ['eredivisie']],
        'soccer_belgium_first_div' => ['Pro League (Belgique)', 1, ['belgium'], ['jupiler pro league', 'pro league', 'first division a']],
        'soccer_brazil_campeonato' => ['Série A (Brésil)', 1, ['brazil'], ['serie a', 'brasileirao', 'campeonato brasileiro serie a']],
        'soccer_turkey_super_league' => ['Süper Lig', 1, ['turkey', 'turkiye'], ['super lig', 'superlig']],
        'soccer_usa_mls' => ['MLS', 1, ['usa', 'united states'], ['major league soccer', 'mls']],
        'soccer_saudi_arabia_pro_league' => ['Saudi Pro League', 1, ['saudi arabia'], ['pro league', 'saudi pro league', 'roshn saudi league']],
        'soccer_italy_serie_a' => ['Serie A (Italie)', 2, ['italy'], ['serie a']],
        'soccer_efl_champ' => ['Championship', 2, ['england'], ['championship']],
        'soccer_portugal_primeira_liga' => ['Liga Portugal', 2, ['portugal'], ['liga portugal', 'primeira liga', 'liga portugal betclic']],
        'soccer_uefa_champs_league' => ['Ligue des champions', 2, ['world', 'europe'], ['uefa champions league', 'champions league']],
        'soccer_uefa_europa_league' => ['Ligue Europa', 2, ['world', 'europe'], ['uefa europa league', 'europa league']],
        'soccer_uefa_europa_conference_league' => ['Ligue Conférence', 3, ['world', 'europe'], ['uefa europa conference league', 'conference league']],
        'soccer_uefa_nations_league' => ['Ligue des nations', 3, ['world', 'europe'], ['uefa nations league', 'nations league']],
        'soccer_spl' => ['Premiership (Écosse)', 3, ['scotland'], ['premiership']],
        'soccer_germany_bundesliga2' => ['2. Bundesliga', 3, ['germany'], ['2. bundesliga', '2 bundesliga']],
        'soccer_france_ligue_two' => ['Ligue 2', 3, ['france'], ['ligue 2']],
        'soccer_spain_segunda_division' => ['Liga 2 (Espagne)', 3, ['spain'], ['segunda division', 'laliga2', 'la liga 2']],
        'soccer_italy_serie_b' => ['Serie B', 3, ['italy'], ['serie b']],
        'soccer_austria_bundesliga' => ['Bundesliga (Autriche)', 3, ['austria'], ['bundesliga']],
        'soccer_switzerland_superleague' => ['Super League (Suisse)', 3, ['switzerland'], ['super league']],
        'soccer_denmark_superliga' => ['Superliga (Danemark)', 3, ['denmark'], ['superliga']],
        'soccer_norway_eliteserien' => ['Eliteserien', 3, ['norway'], ['eliteserien']],
        'soccer_sweden_allsvenskan' => ['Allsvenskan', 3, ['sweden'], ['allsvenskan']],
        'soccer_greece_super_league' => ['Super League (Grèce)', 3, ['greece'], ['super league', 'super league 1']],
        'soccer_poland_ekstraklasa' => ['Ekstraklasa', 3, ['poland'], ['ekstraklasa']],
        'soccer_argentina_primera_division' => ['Liga Profesional (Argentine)', 3, ['argentina'], ['liga profesional', 'primera division', 'liga profesional argentina']],
        'soccer_mexico_ligamx' => ['Liga MX', 3, ['mexico'], ['liga mx']],
        'soccer_japan_j_league' => ['J1 League', 3, ['japan'], ['j1 league', 'j. league', 'j league']],
        'soccer_korea_kleague1' => ['K League 1', 3, ['south korea', 'korea republic'], ['k league 1', 'k-league 1']],
        'soccer_australia_aleague' => ['A-League', 3, ['australia'], ['a-league', 'a league']],
        'soccer_china_superleague' => ['Super League (Chine)', 3, ['china'], ['super league']],
        'soccer_chile_campeonato' => ['Primera División (Chili)', 3, ['chile'], ['primera division']],
        'soccer_fa_cup' => ['FA Cup', 3, ['england'], ['fa cup']],
    ];

    // Sports de relais quand le football ne donne rien de fiable (consignes : basket, tennis, handball).
    public const OTHER_SPORTS = [
        'basketball_nba' => ['NBA', 'basketball'],
        'basketball_euroleague' => ['EuroLeague', 'basketball'],
        'basketball_nbl' => ['NBL (Australie)', 'basketball'],
        'handball_germany_bundesliga' => ['Handball-Bundesliga', 'handball'],
    ];

    /**
     * @param array<string, mixed> $match match Highlightly (league.id, league.name, country.name)
     * @return array{odds_key: string, label: string, priority: int}|null
     */
    public static function resolve(array $match): ?array
    {
        $key = self::BY_ID[(int) ($match['league']['id'] ?? 0)] ?? null;

        if ($key === null) {
            $country = self::normalize((string) ($match['country']['name'] ?? ''));
            $name = self::normalize((string) ($match['league']['name'] ?? ''));

            foreach (self::LEAGUES as $candidate => [, , $countries, $names]) {
                if (in_array($country, $countries, true) && in_array($name, $names, true)) {
                    $key = $candidate;
                    break;
                }
            }
        }

        if ($key === null || ! isset(self::LEAGUES[$key])) {
            return null;
        }

        return ['odds_key' => $key, 'label' => self::LEAGUES[$key][0], 'priority' => self::LEAGUES[$key][1]];
    }

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;

        return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9. -]+/', ' ', $value)));
    }
}
