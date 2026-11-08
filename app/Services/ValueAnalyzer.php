<?php

namespace App\Services;

/**
 * Transforme les données collectées en sélections candidates, chiffrées et vérifiées.
 * Tous les calculs sont faits ici, pas par l'IA : elle ne peut ni inventer une cote,
 * ni gonfler une probabilité.
 *
 * Pour chaque issue (1, N, 2, plus/moins de buts) :
 * - probabilité du marché sans la marge (consensus des bookmakers) ;
 * - probabilité d'un modèle de Poisson (buts attendus tirés du classement domicile/extérieur
 *   et des 5 derniers matchs) ;
 * - probabilité du modèle Highlightly (1N2 seulement), quand elle existe.
 * Ma probabilité = moyenne pondérée de ces sources. Le marché pèse le plus : il est
 * très efficient sur les grands championnats, la valeur y est rare.
 *
 * Deux catégories de sélections candidates :
 * - « valeur » (prioritaire) : cote ≥ 1,30, ma probabilité dépasse la probabilité implicite de la
 *   cote (1 / cote) d'au moins 3 points, valeur = p × cote − 1 positive, au moins une source
 *   indépendante du marché, écart modèle/marché ≤ 30 points, confiance au moins « Moyenne » ;
 * - « solide » : sans valeur prouvée, mais très probable et confirmée par les données : cote
 *   entre 1,15 et 3, ma probabilité ≥ 55 %, les modèles (forme, classement) ne contredisent pas
 *   le marché, et un prix proche du juste prix (valeur ≥ −6 %). Sur les grands championnats, le
 *   marché est si efficient que la « valeur » manque presque tous les jours : ces sélections
 *   permettent de publier chaque jour les paris les mieux couverts par l'analyse.
 */
class ValueAnalyzer
{
    public const MIN_ODDS = 1.30;

    public const MIN_EDGE = 0.03;

    private const STRONG_EDGE = 0.05;

    private const MAX_MODEL_GAP = 0.30;

    private const WEIGHTS = ['market' => 0.60, 'poisson' => 0.25, 'highlightly' => 0.15];

    public const MIN_SOLID_ODDS = 1.15;

    private const MAX_SOLID_ODDS = 3.0;

    private const MIN_SOLID_PROBABILITY = 0.55;

    private const MIN_SOLID_VALUE = -0.06;

    // Un modèle indépendant plus de 8 points sous le marché = les données ne confirment pas.
    private const MAX_SOLID_DISAGREEMENT = 0.08;

    // Double chance : somme de deux issues du 1N2.
    private const DOUBLE_CHANCE = ['home_draw' => ['home', 'draw'], 'draw_away' => ['draw', 'away'], 'home_away' => ['home', 'away']];

    // Basket, handball : sans statistiques de forme, le consensus d'au moins 3 bookmakers est exigé.
    private const MIN_CONSENSUS_BOOKMAKERS = 3;

    // Réserves de paris (valeur/solide et secours) : 40 paris, au plus 3 par match. Sans cette
    // limite, chaque match apportant ~10 paris très probables (1X, 12, +1,5 but…), les 40 venaient
    // de 4 ou 5 matchs seulement : une fois retirés les matchs déjà pris par les autres coupons,
    // Gold (4) et Platinum (5) ne pouvaient plus atteindre leur nombre d'événements et sortaient en
    // « coupon de secours » à 1-2 sélections (constat du 2026-11-08, plainte du fondateur).
    private const RESERVE_SIZE = 40;

    private const MAX_PER_MATCH = 3;

    /**
     * @param array<int, array<string, mixed>> $fixtures matchs enrichis (OddsService + MatchDataService)
     * @return array<int, array<string, mixed>> candidats (« valeur » d'abord, par valeur décroissante,
     *         puis « solide », du plus sûr au moins sûr), avec un identifiant « C1 », « C2 »…
     */
    public function candidates(array $fixtures): array
    {
        $value = [];
        $solid = [];

        foreach ($fixtures as $fixture) {
            foreach ($this->analyseFixture($fixture)['selections'] as $selection) {
                if ($selection['retenue']) {
                    $value[] = [...$selection, 'categorie' => 'valeur'];
                } elseif ($selection['solide']) {
                    $solid[] = [...$selection, 'categorie' => ($fixture['sport'] ?? 'football') === 'football' ? 'solide' : 'consensus'];
                }
            }
        }

        usort($value, fn ($a, $b) => $b['valeur'] <=> $a['valeur']);
        usort($solid, fn ($a, $b) => self::score($b) <=> self::score($a));
        $candidates = $this->diversified([...$value, ...$solid]);

        foreach ($candidates as $index => &$candidate) {
            $candidate['id'] = 'C' . ($index + 1);
        }

        return $candidates;
    }

    /**
     * Réserve de secours, utilisée seulement quand aucun coupon ne passe les règles normales :
     * tous les paris cotés par le marché (cote 1,15 à 3, pas de match nul sec), même sans
     * confirmation par les données, du plus probable au moins probable. Identifiants « S1 », « S2 »…
     *
     * @param array<int, array<string, mixed>> $fixtures
     * @return array<int, array<string, mixed>>
     */
    public function fallbackCandidates(array $fixtures): array
    {
        $fallback = [];

        foreach ($fixtures as $fixture) {
            $football = ($fixture['sport'] ?? 'football') === 'football';

            foreach ($this->analyseFixture($fixture)['selections'] as $selection) {
                if ($selection['probabilite'] === null
                    || $selection['prob_marche'] === null
                    || (float) $selection['cote'] < self::MIN_SOLID_ODDS
                    || (float) $selection['cote'] > self::MAX_SOLID_ODDS
                    || ($football && $selection['selection_key'] === 'draw')) {
                    continue;
                }

                $category = $selection['retenue'] ? 'valeur' : ($selection['solide'] ? ($football ? 'solide' : 'consensus') : 'secours');
                $fallback[] = [...$selection, 'categorie' => $category, 'confiance' => $category === 'secours' ? 'Faible' : $selection['confiance']];
            }
        }

        usort($fallback, fn ($a, $b) => (float) $b['probabilite'] <=> (float) $a['probabilite']);
        $fallback = $this->diversified($fallback);

        foreach ($fallback as $index => &$candidate) {
            $candidate['id'] = 'S' . ($index + 1);
        }

        return $fallback;
    }

    /**
     * Les RESERVE_SIZE premiers paris dans l'ordre reçu, au plus MAX_PER_MATCH par match.
     *
     * @param array<int, array<string, mixed>> $selections
     * @return array<int, array<string, mixed>>
     */
    private function diversified(array $selections): array
    {
        $perMatch = [];
        $kept = [];

        foreach ($selections as $selection) {
            $match = (string) $selection['match'];

            if (($perMatch[$match] ?? 0) >= self::MAX_PER_MATCH) {
                continue;
            }

            $perMatch[$match] = ($perMatch[$match] ?? 0) + 1;
            $kept[] = $selection;

            if (count($kept) >= self::RESERVE_SIZE) {
                break;
            }
        }

        return $kept;
    }

    /**
     * Ordre des sélections solides : la probabilité d'abord, un bonus pour un prix favorable.
     */
    public static function score(array $selection): float
    {
        return (float) $selection['probabilite'] + 0.5 * (float) $selection['valeur'];
    }

    /**
     * Analyse complète d'un match : buts attendus, fiabilité, données manquantes et toutes les
     * sélections (retenues ou non, avec la raison du rejet).
     */
    public function analyseFixture(array $fixture): array
    {
        $data = $fixture['data'] ?? [];
        $football = ($fixture['sport'] ?? 'football') === 'football';
        $expected = $football ? $this->expectedGoals($data) : null;
        $missing = $football
            ? $this->missingData($data)
            : ['forme, classement, blessures et compositions : donnée manquante (seules les cotes de plusieurs bookmakers sont disponibles pour ce sport)'];
        $selections = [];

        foreach ($fixture['selections'] ?? [] as $selection) {
            $selections[] = $this->evaluate($fixture, $selection, $expected, $missing);
        }

        return ['expected' => $expected, 'missing' => $missing, 'selections' => $selections];
    }

    private function evaluate(array $fixture, array $selection, ?array $expected, array $missing): array
    {
        $key = (string) $selection['key'];
        $cote = (float) $selection['cote'];
        $line = isset($selection['point']) ? (float) $selection['point'] : null;
        $data = $fixture['data'] ?? [];

        $sources = [];

        if (isset($selection['fair']) && $selection['fair'] !== null) {
            $sources['market'] = (float) $selection['fair'];
        }

        $poisson = $expected !== null && $expected['fiabilite'] !== 'faible'
            ? $this->poissonProbability($key, $expected['home'], $expected['away'], $line)
            : null;

        if ($poisson !== null) {
            $sources['poisson'] = $poisson;
        }

        $predictions = $data['details']['predictions'] ?? null;
        if (is_array($predictions) && isset($predictions[$key])) {
            $sources['highlightly'] = (float) $predictions[$key];
        } elseif (is_array($predictions) && isset(self::DOUBLE_CHANCE[$key])) {
            [$first, $second] = self::DOUBLE_CHANCE[$key];

            if (isset($predictions[$first], $predictions[$second])) {
                $sources['highlightly'] = (float) $predictions[$first] + (float) $predictions[$second];
            }
        }

        $weight = array_sum(array_intersect_key(self::WEIGHTS, $sources));
        $probability = $weight > 0
            ? array_sum(array_map(fn ($name) => $sources[$name] * self::WEIGHTS[$name], array_keys($sources))) / $weight
            : null;

        $implied = $cote > 1 ? 1 / $cote : 1.0;
        $value = $probability !== null ? $probability * $cote - 1 : null;
        $edge = $probability !== null ? $probability - $implied : null;
        $risks = $missing;

        $firstCote = (float) ($selection['first_cote'] ?? $cote);
        if ($firstCote > 0 && $cote < $firstCote * 0.9) {
            $risks[] = sprintf('cote en forte baisse depuis ce matin (%.2f → %.2f) : information possible non connue', $firstCote, $cote);
        }

        $rejection = match (true) {
            $cote < self::MIN_ODDS => 'cote inférieure à 1,30',
            ! isset($sources['market']) => 'marché incomplet : marge impossible à retirer',
            ! isset($sources['poisson']) && ! isset($sources['highlightly']) => 'aucune donnée indépendante du marché (forme, classement)',
            isset($sources['poisson']) && abs($sources['poisson'] - $sources['market']) > self::MAX_MODEL_GAP => 'écart énorme entre le modèle et le marché : information probablement manquante',
            $edge < self::MIN_EDGE => 'pas de valeur : ma probabilité ne dépasse pas la probabilité implicite de 3 points',
            default => null,
        };

        $confidence = $rejection === null ? $this->confidence($sources, $implied, $edge, $data) : 'Faible';

        if ($rejection === null && $confidence === 'Faible') {
            $rejection = 'confiance faible';
        }

        $solidRejection = $rejection === null
            ? null
            : $this->solidRejection($sources, $cote, $probability, $value, ($fixture['sport'] ?? 'football') === 'football', (int) ($selection['bookmakers'] ?? 1));

        if ($rejection !== null && $solidRejection === null) {
            $confidence = 'Moyenne';
        }

        if ($selection['derived'] ?? false) {
            $risks[] = 'cote calculée à partir des cotes 1N2 (vérifier la cote double chance sur 1xBet / Melbet)';
        }

        return [
            'match' => $fixture['match'],
            'competition' => $fixture['competition'] ?? '',
            'pronostic' => $selection['label'],
            'cote' => round($cote, 2),
            'meilleure_cote' => round((float) ($selection['best'] ?? $cote), 2),
            'bookmakers' => (int) ($selection['bookmakers'] ?? 1),
            'probabilite' => $probability !== null ? round($probability, 4) : null,
            'prob_implicite' => round($implied, 4),
            'prob_marche' => isset($sources['market']) ? round($sources['market'], 4) : null,
            'prob_poisson' => isset($sources['poisson']) ? round($sources['poisson'], 4) : null,
            'prob_highlightly' => isset($sources['highlightly']) ? round($sources['highlightly'], 4) : null,
            'valeur' => $value !== null ? round($value, 4) : null,
            'confiance' => $confidence,
            'risques' => $risks,
            'retenue' => $rejection === null,
            'rejet' => $rejection,
            'solide' => $rejection !== null && $solidRejection === null,
            'fixture_id' => $fixture['id'] ?? null,
            'sport' => $fixture['sport'] ?? 'football',
            'odds_key' => $fixture['odds_key'] ?? null,
            'event_id' => $fixture['event_id'] ?? null,
            'league_id' => $fixture['league_id'] ?? null,
            'kickoff' => $fixture['kickoff'] ?? null,
            'kickoff_at' => $fixture['kickoff_at'] ?? null,
            'home' => $fixture['home'] ?? '',
            'away' => $fixture['away'] ?? '',
            'selection_key' => $key,
            'line' => $line,
        ];
    }

    /**
     * Raison pour laquelle une sélection sans valeur n'est pas non plus « solide » (null si elle l'est).
     */
    private function solidRejection(array $sources, float $cote, ?float $probability, ?float $value, bool $football = true, int $bookmakers = 1): ?string
    {
        if (! isset($sources['market']) || $probability === null || $value === null) {
            return 'marché incomplet';
        }

        $independent = array_intersect_key($sources, array_flip(['poisson', 'highlightly']));

        if (! $football) {
            return match (true) {
                $cote < self::MIN_SOLID_ODDS || $cote > self::MAX_SOLID_ODDS => 'cote hors de la zone prudente (1,15 à 3)',
                $bookmakers < self::MIN_CONSENSUS_BOOKMAKERS => 'moins de 3 bookmakers : consensus insuffisant',
                $probability < self::MIN_SOLID_PROBABILITY => 'probabilité inférieure à 55 %',
                $value < self::MIN_SOLID_VALUE => 'cote trop basse pour sa probabilité',
                default => null,
            };
        }

        return match (true) {
            $cote < self::MIN_SOLID_ODDS || $cote > self::MAX_SOLID_ODDS => 'cote hors de la zone prudente (1,15 à 3)',
            $independent === [] => 'aucune donnée indépendante du marché',
            min($independent) < $sources['market'] - self::MAX_SOLID_DISAGREEMENT => 'les données ne confirment pas le marché',
            max($independent) > $sources['market'] + self::MAX_MODEL_GAP => 'écart énorme entre le modèle et le marché',
            $probability < self::MIN_SOLID_PROBABILITY => 'probabilité inférieure à 55 %',
            $value < self::MIN_SOLID_VALUE => 'cote trop basse pour sa probabilité',
            default => null,
        };
    }

    /**
     * Élevée : au moins 5 points d'avance, les deux sources indépendantes au-dessus de la probabilité
     * implicite, et un vrai championnat. Composition jamais publiée au moment de la génération
     * (Highlightly la publie 30 min avant le match) : la confiance reste au plus « Moyenne »
     * sauf si tout le reste est solide.
     */
    private function confidence(array $sources, float $implied, float $edge, array $data): string
    {
        $independent = array_filter(
            array_intersect_key($sources, array_flip(['poisson', 'highlightly'])),
            fn ($probability) => $probability > $implied,
        );

        if ($independent === []) {
            return 'Faible';
        }

        $strong = $edge >= self::STRONG_EDGE
            && count($independent) === 2
            && ($data['type'] ?? 'championnat') === 'championnat';

        return $strong ? 'Élevée' : 'Moyenne';
    }

    /**
     * Buts attendus des deux équipes : 60 % bilan de la saison (domicile pour l'équipe qui reçoit,
     * extérieur pour l'autre), 40 % les 5 derniers matchs. Attaque de l'un et défense de l'autre
     * sont moyennées.
     *
     * @return array{home: float, away: float, fiabilite: string, matchs: int}|null
     */
    public function expectedGoals(array $data): ?array
    {
        $standings = $data['standings'] ?? null;
        $formHome = $data['form']['home'] ?? null;
        $formAway = $data['form']['away'] ?? null;

        $season = function (?array $team, string $venue): ?array {
            if (! $team) {
                return null;
            }

            $split = ($team[$venue]['games'] ?? 0) >= 3 ? $team[$venue] : ($team['total'] ?? null);

            if (! $split || ($split['games'] ?? 0) < 3) {
                return null;
            }

            return ['for' => $split['scored'] / $split['games'], 'against' => $split['received'] / $split['games'], 'games' => $split['games']];
        };

        $recent = function (?array $games): ?array {
            if (! $games || count($games) < 3) {
                return null;
            }

            return [
                'for' => array_sum(array_column($games, 'gf')) / count($games),
                'against' => array_sum(array_column($games, 'ga')) / count($games),
                'games' => count($games),
            ];
        };

        $blend = function (?array $seasonStats, ?array $recentStats): ?array {
            if ($seasonStats && $recentStats) {
                return [
                    'for' => 0.6 * $seasonStats['for'] + 0.4 * $recentStats['for'],
                    'against' => 0.6 * $seasonStats['against'] + 0.4 * $recentStats['against'],
                    'games' => $seasonStats['games'] + $recentStats['games'],
                ];
            }

            return $seasonStats ?? $recentStats;
        };

        $home = $blend($season($standings['home'] ?? null, 'home'), $recent($formHome));
        $away = $blend($season($standings['away'] ?? null, 'away'), $recent($formAway));

        if (! $home || ! $away) {
            return null;
        }

        $sample = min($home['games'], $away['games']);

        return [
            'home' => round(max(0.2, min(4.0, ($home['for'] + $away['against']) / 2)), 3),
            'away' => round(max(0.2, min(4.0, ($away['for'] + $home['against']) / 2)), 3),
            // Moins de 5 matchs = faible fiabilité.
            'fiabilite' => $sample < 5 ? 'faible' : ($sample < 10 ? 'moyenne' : 'bonne'),
            'matchs' => $sample,
        ];
    }

    /**
     * P(1), P(N), P(2), P(plus/moins de X buts) et P(les deux marquent) par une loi de Poisson.
     *
     * @return array{home: float, draw: float, away: float, btts: float, over25: float}
     */
    public function poissonTable(float $lambdaHome, float $lambdaAway, int $max = 10): array
    {
        $home = $draw = $away = $btts = $over25 = 0.0;

        for ($h = 0; $h <= $max; $h++) {
            for ($a = 0; $a <= $max; $a++) {
                $p = $this->poisson($h, $lambdaHome) * $this->poisson($a, $lambdaAway);

                if ($h > $a) {
                    $home += $p;
                } elseif ($h === $a) {
                    $draw += $p;
                } else {
                    $away += $p;
                }

                if ($h > 0 && $a > 0) {
                    $btts += $p;
                }

                if ($h + $a > 2.5) {
                    $over25 += $p;
                }
            }
        }

        return compact('home', 'draw', 'away', 'btts', 'over25');
    }

    private function poissonProbability(string $key, float $lambdaHome, float $lambdaAway, ?float $line): ?float
    {
        if (in_array($key, ['home', 'draw', 'away'], true)) {
            return $this->poissonTable($lambdaHome, $lambdaAway)[$key];
        }

        if (isset(self::DOUBLE_CHANCE[$key])) {
            $table = $this->poissonTable($lambdaHome, $lambdaAway);

            return $table[self::DOUBLE_CHANCE[$key][0]] + $table[self::DOUBLE_CHANCE[$key][1]];
        }

        if ($key === 'btts_yes' || $key === 'btts_no') {
            $btts = $this->poissonTable($lambdaHome, $lambdaAway)['btts'];

            return $key === 'btts_yes' ? $btts : 1 - $btts;
        }

        if (! in_array($key, ['over', 'under'], true) || $line === null) {
            return null;
        }

        // Ligne entière (2.0, 3.0) : le score exact sur la ligne est remboursé, donc exclu.
        $over = $under = 0.0;
        $lambda = $lambdaHome + $lambdaAway;

        for ($goals = 0; $goals <= 15; $goals++) {
            $p = $this->poisson($goals, $lambda);

            if ($goals > $line) {
                $over += $p;
            } elseif ($goals < $line) {
                $under += $p;
            }
        }

        $total = $over + $under;

        if ($total <= 0) {
            return null;
        }

        return $key === 'over' ? $over / $total : $under / $total;
    }

    private function poisson(int $k, float $lambda): float
    {
        $factorial = 1;
        for ($i = 2; $i <= $k; $i++) {
            $factorial *= $i;
        }

        return ($lambda ** $k) * exp(-$lambda) / $factorial;
    }

    /**
     * @return array<int, string>
     */
    public function missingData(array $data): array
    {
        $missing = [
            'xG/xGA : donnée manquante (non fournie par l’API)',
            'blessés et suspendus : donnée manquante',
            'composition probable : non publiée au moment de l’analyse',
        ];

        if (empty($data['standings'])) {
            $missing[] = 'classement : donnée manquante';
        }

        foreach (['home' => 'qui reçoit', 'away' => 'qui se déplace'] as $side => $label) {
            $games = $data['form'][$side] ?? null;

            if (! $games) {
                $missing[] = "forme de l’équipe {$label} : donnée manquante";
            } elseif (count($games) < 5) {
                $missing[] = "forme de l’équipe {$label} : moins de 5 matchs, faible fiabilité";
            }
        }

        if (($data['type'] ?? '') === 'coupe') {
            $missing[] = 'coupe / compétition européenne : rotation et format aller-retour possibles';
        } elseif (($data['type'] ?? '') === 'selections') {
            $missing[] = 'match de sélections : effectifs changeants, fiabilité plus basse';
        }

        return $missing;
    }
}
