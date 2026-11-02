<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Support\LeagueCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cotes réelles de The Odds API (https://the-odds-api.com), attachées aux matchs avant l'analyse.
 *
 * Budget : le quota du mois est réparti jour par jour (crédits restants ÷ jours restants), et
 * tous les créneaux de la journée partagent les cotes déjà relevées. Avec l'offre gratuite
 * (500 crédits/mois), seuls les championnats prioritaires sont relevés ; avec un abonnement,
 * la recherche s'élargit d'elle-même (plus de championnats, marchés « les deux équipes
 * marquent » et lignes de buts 1,5 / 3,5).
 */
class OddsService
{
    private const ENDPOINT = 'https://api.the-odds-api.com/v4/sports';

    // Offre gratuite : quatre championnats par relevé ; abonnement : seize.
    public const MAX_SPORTS = 4;

    private const MAX_SPORTS_EXTENDED = 16;

    // Un plan d'au moins 5 000 crédits par mois active la recherche élargie.
    private const EXTENDED_PLAN_CREDITS = 5000;

    // Crédits gardés en réserve pour valider les résultats (basket) jusqu'à la fin du mois.
    private const RESERVE = 10;

    private const USAGE_CACHE_KEY = 'odds_api:usage';

    private const ERROR_CACHE_KEY = 'odds_api:error';

    /**
     * @param array<int, array<string, mixed>> $matches matchs Highlightly (FixtureService) ou saisis à la main
     * @return array<int, array<string, mixed>>
     */
    public function attach(array $matches): array
    {
        $key = AppSetting::value('odds_api_key');

        if (! $key) {
            return $matches;
        }

        // Championnats à relever : les plus prioritaires, puis ceux qui ont le plus de matchs.
        $sports = collect($matches)
            ->filter(fn ($match) => ! empty($this->sportKeyFor($match)))
            ->groupBy(fn ($match) => $this->sportKeyFor($match))
            ->sortBy(fn ($group) => [(int) ($group->first()['priority'] ?? 2), -$group->count()])
            ->keys()
            ->take(self::extended() ? self::MAX_SPORTS_EXTENDED : self::MAX_SPORTS)
            ->all();

        $events = [];

        foreach ($sports as $sport) {
            foreach ($this->featuredOdds($key, $sport) as $event) {
                $events[$sport][] = $event;
            }
        }

        $attached = array_map(function (array $match) use ($events) {
            [$home, $away] = $this->teams($match);
            $event = $this->findEvent($events[$this->sportKeyFor($match)] ?? [], $home, $away, $match['kickoff_at'] ?? null);
            $match['home'] = $home;
            $match['away'] = $away;
            $match['selections'] = $event ? $this->withMovement(self::matchKey($home, $away), $this->extractSelections($event, $home, $away)) : [];
            $match['event_id'] = $event['id'] ?? null;

            return $match;
        }, $matches);

        return $this->withExtraMarkets($key, $attached);
    }

    /**
     * Sport de relais (basket, handball) : les rencontres des prochaines heures avec leurs cotes
     * (vainqueur, handicap, total). Aucune statistique de forme n'est disponible pour ces sports :
     * l'analyse s'appuie sur le consensus des bookmakers et le signale.
     *
     * @return array<int, array<string, mixed>>
     */
    public function otherSport(string $sport, Carbon $from, Carbon $to): array
    {
        $key = AppSetting::value('odds_api_key');
        [$label, $sportName] = LeagueCatalog::OTHER_SPORTS[$sport] ?? [null, null];

        if (! $key || ! $label) {
            return [];
        }

        $timezone = config('app.timezone', 'UTC') === 'UTC' ? 'Etc/UTC' : config('app.timezone');

        return collect($this->featuredOdds($key, $sport, 'h2h,spreads,totals'))
            ->filter(function ($event) use ($from, $to) {
                $start = isset($event['commence_time']) ? Carbon::parse($event['commence_time']) : null;

                return $start && $start->gte($from) && $start->lte($to);
            })
            ->map(fn ($event) => [
                'id' => $event['id'],
                'event_id' => $event['id'],
                'sport' => $sportName,
                'odds_key' => $sport,
                'priority' => 4,
                'match' => $event['home_team'] . ' vs ' . $event['away_team'],
                'competition' => $label,
                'home' => $event['home_team'],
                'away' => $event['away_team'],
                'kickoff_at' => $event['commence_time'],
                'kickoff' => Carbon::parse($event['commence_time'])->setTimezone($timezone)->format('H:i'),
                'selections' => $this->extractSelections($event, $event['home_team'], $event['away_team'], $sportName),
                'data' => ['type' => $sportName],
            ])
            ->values()
            ->all();
    }

    /**
     * Scores finaux d'un sport (validation des combinés de basket ou de handball), gardés 1 h.
     *
     * @return array<string, array{home_score: ?int, away_score: ?int, completed: bool}>
     */
    public function scores(string $sport): array
    {
        $key = AppSetting::value('odds_api_key');

        if (! $key) {
            return [];
        }

        $cacheKey = 'odds:scores:' . $sport;
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        if (! self::canSpend(2, essential: true)) {
            return [];
        }

        $response = $this->call($key, self::ENDPOINT . "/{$sport}/scores", ['daysFrom' => 2]);
        $scores = [];

        foreach ($response ?? [] as $event) {
            $points = collect($event['scores'] ?? [])->pluck('score', 'name');
            $scores[(string) $event['id']] = [
                'home_score' => isset($points[$event['home_team'] ?? '']) ? (int) $points[$event['home_team']] : null,
                'away_score' => isset($points[$event['away_team'] ?? '']) ? (int) $points[$event['away_team']] : null,
                'completed' => (bool) ($event['completed'] ?? false),
            ];
        }

        Cache::put($cacheKey, $scores, now()->addHour());

        return $scores;
    }

    /**
     * Crédits restants, relevés à chaque appel, budget du jour et dernière erreur (Admin > Pronostics).
     *
     * @return array{remaining: ?int, used: ?int, at: ?string, error: ?string, extended: bool, allowance: int, spent_today: int}
     */
    public static function usage(): array
    {
        $usage = Cache::get(self::USAGE_CACHE_KEY, []);

        return [
            'remaining' => $usage['remaining'] ?? null,
            'used' => $usage['used'] ?? null,
            'at' => $usage['at'] ?? null,
            'error' => Cache::get(self::ERROR_CACHE_KEY),
            'extended' => self::extended(),
            'allowance' => self::allowanceToday(),
            'spent_today' => (int) Cache::get(self::spentKey(), 0),
        ];
    }

    /**
     * Abonnement payant détecté (plan d'au moins 5 000 crédits par mois).
     */
    public static function extended(): bool
    {
        $usage = Cache::get(self::USAGE_CACHE_KEY, []);

        return isset($usage['remaining'], $usage['used'])
            && ((int) $usage['remaining'] + (int) $usage['used']) >= self::EXTENDED_PLAN_CREDITS;
    }

    /**
     * Crédits que la journée peut dépenser : le reste du mois réparti sur les jours restants.
     */
    public static function allowanceToday(): int
    {
        $usage = Cache::get(self::USAGE_CACHE_KEY, []);
        $startOfDay = Cache::get('odds_api:day_start:' . now()->toDateString());
        $remaining = (int) ($startOfDay ?? $usage['remaining'] ?? 500);
        $daysLeft = max(1, now()->daysInMonth - now()->day + 1);

        return max(6, intdiv(max(0, $remaining - self::RESERVE), $daysLeft));
    }

    private static function canSpend(int $credits, bool $essential = false): bool
    {
        $usage = Cache::get(self::USAGE_CACHE_KEY, []);

        if (isset($usage['remaining']) && (int) $usage['remaining'] < $credits + ($essential ? 0 : self::RESERVE)) {
            return false;
        }

        return $essential || (int) Cache::get(self::spentKey(), 0) + $credits <= self::allowanceToday();
    }

    private static function spentKey(): string
    {
        return 'odds_api:spent:' . now()->toDateString();
    }

    /**
     * Cotes principales d'un championnat. Gardées 8 h avec l'offre gratuite (2 h avec un
     * abonnement) ; budget du jour épuisé : les dernières cotes connues (moins de 20 h) servent.
     *
     * @return array<int, array<string, mixed>>
     */
    private function featuredOdds(string $key, string $sport, string $markets = 'h2h,totals'): array
    {
        $cacheKey = 'odds:sport:' . $sport . ':' . $markets;
        $cached = Cache::get($cacheKey);
        $freshHours = self::extended() ? 2 : 8;

        if (is_array($cached) && Carbon::parse($cached['at'])->gt(now()->subHours($freshHours))) {
            return $cached['events'];
        }

        $cost = count(explode(',', $markets));

        if (! self::canSpend($cost)) {
            // Plutôt des cotes de quelques heures que rien du tout.
            return is_array($cached) && Carbon::parse($cached['at'])->gt(now()->subHours(20)) ? $cached['events'] : [];
        }

        $events = $this->call($key, self::ENDPOINT . "/{$sport}/odds", [
            'regions' => 'eu',
            'markets' => $markets,
            'oddsFormat' => 'decimal',
        ]);

        if ($events === null) {
            return is_array($cached) ? $cached['events'] : [];
        }

        Cache::put($cacheKey, ['at' => now()->toIso8601String(), 'events' => $events], now()->addHours(24));

        return $events;
    }

    /**
     * Abonnement : marchés « les deux équipes marquent » et lignes de buts supplémentaires pour les
     * matchs les mieux placés (une requête par match, d'où le réservé aux plans payants).
     *
     * @param array<int, array<string, mixed>> $matches
     * @return array<int, array<string, mixed>>
     */
    private function withExtraMarkets(string $key, array $matches): array
    {
        if (! self::extended()) {
            return $matches;
        }

        $budget = 8;

        foreach ($matches as &$match) {
            if ($budget <= 0 || empty($match['event_id']) || empty($match['selections']) || ! self::canSpend(2)) {
                continue;
            }

            $cacheKey = 'odds:extra:' . $match['event_id'];
            $event = Cache::get($cacheKey);

            if (! is_array($event)) {
                $event = $this->call($key, self::ENDPOINT . '/' . $this->sportKeyFor($match) . '/events/' . $match['event_id'] . '/odds', [
                    'regions' => 'eu',
                    'markets' => 'btts,alternate_totals',
                    'oddsFormat' => 'decimal',
                ]) ?? [];
                Cache::put($cacheKey, $event, now()->addHours(3));
                $budget--;
            }

            $match['selections'] = [...$match['selections'], ...$this->extraSelections($event, $match['selections'])];
        }

        return $matches;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function call(string $key, string $url, array $query): ?array
    {
        try {
            $response = Http::timeout(15)->get($url, ['apiKey' => $key, ...$query]);
        } catch (Throwable $exception) {
            Log::warning('The Odds API injoignable.', ['url' => $url, 'error' => $exception->getMessage()]);
            self::rememberError('The Odds API injoignable : ' . $exception->getMessage());

            return null;
        }

        if ($response->header('x-requests-remaining') !== '') {
            $remaining = (int) $response->header('x-requests-remaining');
            Cache::forever(self::USAGE_CACHE_KEY, [
                'remaining' => $remaining,
                'used' => (int) $response->header('x-requests-used'),
                'at' => now()->toIso8601String(),
            ]);
            Cache::add('odds_api:day_start:' . now()->toDateString(), $remaining + (int) $response->header('x-requests-last'), now()->addDays(2));
        }

        if ($response->header('x-requests-last') !== '') {
            Cache::put(self::spentKey(), (int) Cache::get(self::spentKey(), 0) + (int) $response->header('x-requests-last'), now()->addDays(2));
        }

        if (! $response->successful()) {
            $message = (string) ($response->json('message') ?? '');
            Log::warning('The Odds API a refusé la demande.', ['url' => $url, 'status' => $response->status(), 'message' => $message]);
            self::rememberError(in_array($response->status(), [401, 429], true)
                ? 'Quota The Odds API épuisé ou clé refusée (HTTP ' . $response->status() . ($message !== '' ? ' : ' . $message : '') . '). Le quota gratuit se recharge le 1er du mois.'
                : 'The Odds API a refusé la demande (HTTP ' . $response->status() . ').');

            return null;
        }

        Cache::forget(self::ERROR_CACHE_KEY);

        return $response->json() ?? [];
    }

    private static function rememberError(string $message): void
    {
        Cache::put(self::ERROR_CACHE_KEY, $message, now()->addDays(2));
    }

    /**
     * Par sélection : cote moyenne (celle proposée au joueur), meilleure cote, nombre de bookmakers
     * et probabilité du marché SANS la marge (chaque bookmaker est normalisé, puis on fait la moyenne).
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractSelections(array $event, string $home, string $away, string $sport = 'football'): array
    {
        $eventHome = (string) ($event['home_team'] ?? '');
        $eventAway = (string) ($event['away_team'] ?? '');
        $unit = $sport === 'football' ? 'buts' : ($sport === 'basketball' ? 'points' : 'buts');

        $h2h = ['home' => [], 'draw' => [], 'away' => []];
        $h2hFair = ['home' => [], 'draw' => [], 'away' => []];
        $doubleChance = ['home_draw' => [], 'draw_away' => [], 'home_away' => []];
        $doubleChanceFair = ['home_draw' => [], 'draw_away' => [], 'home_away' => []];
        $totals = [];
        $spreads = [];

        foreach ($event['bookmakers'] ?? [] as $bookmaker) {
            foreach ($bookmaker['markets'] ?? [] as $market) {
                if ($market['key'] === 'h2h') {
                    $prices = [];

                    foreach ($market['outcomes'] ?? [] as $outcome) {
                        $price = (float) ($outcome['price'] ?? 0);
                        $name = (string) ($outcome['name'] ?? '');

                        if ($price <= 1) {
                            continue;
                        }

                        if ($name === $eventHome) {
                            $prices['home'] = $price;
                        } elseif ($name === $eventAway) {
                            $prices['away'] = $price;
                        } elseif (strcasecmp($name, 'Draw') === 0) {
                            $prices['draw'] = $price;
                        }
                    }

                    foreach ($prices as $key => $price) {
                        $h2h[$key][] = $price;
                    }

                    // Marge retirée seulement si le bookmaker cote toutes les issues du marché.
                    $complete = $sport === 'football' ? count($prices) === 3 : isset($prices['home'], $prices['away']);

                    if ($complete) {
                        $sum = array_sum(array_map(fn ($price) => 1 / $price, $prices));
                        $fair = [];

                        foreach ($prices as $key => $price) {
                            $fair[$key] = (1 / $price) / $sum;
                            $h2hFair[$key][] = $fair[$key];
                        }

                        // Double chance tirée des mêmes cotes 1N2 : la marge des deux issues est gardée,
                        // la cote obtenue est donc prudente (égale ou un peu sous celle des bookmakers).
                        if ($sport === 'football') {
                            foreach (['home_draw' => ['home', 'draw'], 'draw_away' => ['draw', 'away'], 'home_away' => ['home', 'away']] as $dc => [$first, $second]) {
                                $doubleChance[$dc][] = 1 / (1 / $prices[$first] + 1 / $prices[$second]);
                                $doubleChanceFair[$dc][] = $fair[$first] + $fair[$second];
                            }
                        }
                    }
                } elseif ($market['key'] === 'totals') {
                    $this->collectTotals($market, $totals);
                } elseif ($market['key'] === 'spreads') {
                    $pairs = [];

                    foreach ($market['outcomes'] ?? [] as $outcome) {
                        $price = (float) ($outcome['price'] ?? 0);
                        $side = ($outcome['name'] ?? '') === $eventHome ? 'home' : (($outcome['name'] ?? '') === $eventAway ? 'away' : null);

                        if ($price <= 1 || $side === null || ! isset($outcome['point'])) {
                            continue;
                        }

                        $line = sprintf('%.2f', abs((float) $outcome['point']));
                        $spreads[$line]['point'] = abs((float) $outcome['point']);
                        $spreads[$line][$side][] = ['price' => $price, 'point' => (float) $outcome['point']];
                        $pairs[$line][$side] = $price;
                    }

                    foreach ($pairs as $line => $pair) {
                        if (isset($pair['home'], $pair['away'])) {
                            $sum = 1 / $pair['home'] + 1 / $pair['away'];
                            $spreads[$line]['fair_home'][] = (1 / $pair['home']) / $sum;
                            $spreads[$line]['fair_away'][] = (1 / $pair['away']) / $sum;
                        }
                    }
                }
            }
        }

        $selections = [];
        $winners = $sport === 'football'
            ? ['home' => "Victoire {$home}", 'draw' => 'Match nul', 'away' => "Victoire {$away}"]
            : ['home' => "{$home} gagne", 'away' => "{$away} gagne"];

        foreach ($winners as $key => $label) {
            if ($h2h[$key] !== []) {
                $selections[] = $this->selection($key, $label, $h2h[$key], $h2hFair[$key]);
            }
        }

        foreach ([
            'home_draw' => "Double chance 1X ({$home} ou nul)",
            'draw_away' => "Double chance X2 (nul ou {$away})",
            'home_away' => "Double chance 12 ({$home} ou {$away})",
        ] as $key => $label) {
            if ($doubleChance[$key] !== []) {
                $selections[] = [...$this->selection($key, $label, $doubleChance[$key], $doubleChanceFair[$key]), 'derived' => true];
            }
        }

        // Ligne de total la plus représentée chez les bookmakers (2.5, 3.5… ; 215.5 en basket).
        $best = collect($totals)->sortByDesc(fn ($data) => max(count($data['over']), count($data['under'])))->first();

        if ($best !== null && $best['over'] !== [] && $best['under'] !== []) {
            $label = self::line($best['point']);
            $selections[] = [...$this->selection('over', "Plus de {$label} {$unit}", $best['over'], $best['fair_over']), 'point' => $best['point']];
            $selections[] = [...$this->selection('under', "Moins de {$label} {$unit}", $best['under'], $best['fair_under']), 'point' => $best['point']];
        }

        $spread = collect($spreads)->sortByDesc(fn ($data) => count($data['home'] ?? []) + count($data['away'] ?? []))->first();

        if ($spread !== null && ! empty($spread['home']) && ! empty($spread['away'])) {
            foreach (['home' => $home, 'away' => $away] as $side => $team) {
                $point = $spread[$side][0]['point'];
                $selections[] = [
                    ...$this->selection('spread_' . $side, "Handicap {$team} " . ($point > 0 ? '+' : '') . self::line($point), array_column($spread[$side], 'price'), $spread['fair_' . $side] ?? []),
                    'point' => $point,
                ];
            }
        }

        return $selections;
    }

    /**
     * Marchés par match (abonnement) : les deux équipes marquent, lignes 1,5 et 3,5 buts.
     *
     * @param array<int, array<string, mixed>> $existing
     * @return array<int, array<string, mixed>>
     */
    private function extraSelections(array $event, array $existing): array
    {
        $btts = ['yes' => [], 'no' => []];
        $bttsFair = ['yes' => [], 'no' => []];
        $totals = [];

        foreach ($event['bookmakers'] ?? [] as $bookmaker) {
            foreach ($bookmaker['markets'] ?? [] as $market) {
                if ($market['key'] === 'btts') {
                    $prices = [];

                    foreach ($market['outcomes'] ?? [] as $outcome) {
                        $side = strcasecmp((string) ($outcome['name'] ?? ''), 'Yes') === 0 ? 'yes' : 'no';
                        $prices[$side] = (float) ($outcome['price'] ?? 0);
                        $btts[$side][] = $prices[$side];
                    }

                    if (($prices['yes'] ?? 0) > 1 && ($prices['no'] ?? 0) > 1) {
                        $sum = 1 / $prices['yes'] + 1 / $prices['no'];
                        $bttsFair['yes'][] = (1 / $prices['yes']) / $sum;
                        $bttsFair['no'][] = (1 / $prices['no']) / $sum;
                    }
                } elseif ($market['key'] === 'alternate_totals') {
                    $this->collectTotals($market, $totals);
                }
            }
        }

        $selections = [];

        if ($btts['yes'] !== [] && $btts['no'] !== []) {
            $selections[] = $this->selection('btts_yes', 'Les deux équipes marquent : oui', $btts['yes'], $bttsFair['yes']);
            $selections[] = $this->selection('btts_no', 'Les deux équipes marquent : non', $btts['no'], $bttsFair['no']);
        }

        $existingLines = collect($existing)->pluck('point')->filter()->map(fn ($point) => sprintf('%.2f', $point))->all();

        foreach ([1.5, 3.5] as $point) {
            $line = sprintf('%.2f', $point);
            $data = $totals[$line] ?? null;

            if ($data === null || in_array($line, $existingLines, true) || $data['over'] === [] || $data['under'] === []) {
                continue;
            }

            $selections[] = [...$this->selection('over', 'Plus de ' . self::line($point) . ' buts', $data['over'], $data['fair_over']), 'point' => $point];
            $selections[] = [...$this->selection('under', 'Moins de ' . self::line($point) . ' buts', $data['under'], $data['fair_under']), 'point' => $point];
        }

        return $selections;
    }

    /**
     * @param array<string, mixed> $market
     * @param array<string, array<string, mixed>> $totals
     */
    private function collectTotals(array $market, array &$totals): void
    {
        $pairs = [];

        foreach ($market['outcomes'] ?? [] as $outcome) {
            $price = (float) ($outcome['price'] ?? 0);
            $point = (float) ($outcome['point'] ?? 0);
            $name = (string) ($outcome['name'] ?? '');

            if ($price <= 1 || $point <= 0) {
                continue;
            }

            $key = sprintf('%.2f', $point);
            $totals[$key] ??= ['point' => $point, 'over' => [], 'under' => [], 'fair_over' => [], 'fair_under' => []];

            if (stripos($name, 'Over') !== false) {
                $totals[$key]['over'][] = $price;
                $pairs[$key]['over'] = $price;
            } elseif (stripos($name, 'Under') !== false) {
                $totals[$key]['under'][] = $price;
                $pairs[$key]['under'] = $price;
            }
        }

        foreach ($pairs as $key => $pair) {
            if (isset($pair['over'], $pair['under'])) {
                $sum = 1 / $pair['over'] + 1 / $pair['under'];
                $totals[$key]['fair_over'][] = (1 / $pair['over']) / $sum;
                $totals[$key]['fair_under'][] = (1 / $pair['under']) / $sum;
            }
        }
    }

    /**
     * @param array<int, float> $prices
     * @param array<int, float> $fair
     */
    private function selection(string $key, string $label, array $prices, array $fair): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'cote' => round(array_sum($prices) / count($prices), 2),
            'best' => round(max($prices), 2),
            'bookmakers' => count($prices),
            'fair' => $fair !== [] ? round(array_sum($fair) / count($fair), 4) : null,
        ];
    }

    /**
     * Mouvement des cotes : la première cote relevée dans la journée est gardée 30 h et comparée
     * aux suivantes. Une baisse forte peut signaler une info (blessure, composition) que l'analyse n'a pas.
     *
     * @param array<int, array<string, mixed>> $selections
     */
    private function withMovement(string $matchKey, array $selections): array
    {
        return array_map(function (array $selection) use ($matchKey) {
            $cacheKey = 'odds:first:' . now()->toDateString() . ':' . md5($matchKey . '|' . $selection['key'] . '|' . ($selection['point'] ?? ''));
            Cache::add($cacheKey, $selection['cote'], now()->addHours(30));
            $first = (float) Cache::get($cacheKey, $selection['cote']);

            return [...$selection, 'first_cote' => round($first, 2)];
        }, $selections);
    }

    /**
     * Rencontre correspondante chez The Odds API : noms d'équipes proches (« Paris Saint Germain » /
     * « PSG », « Wolverhampton Wanderers » / « Wolves ») et coup d'envoi à moins de 3 h.
     *
     * @param array<int, array<string, mixed>> $events
     */
    private function findEvent(array $events, string $home, string $away, ?string $kickoffAt): ?array
    {
        $best = null;
        $bestScore = 0.0;

        foreach ($events as $event) {
            if ($kickoffAt && isset($event['commence_time'])
                && abs(Carbon::parse($event['commence_time'])->diffInMinutes(Carbon::parse($kickoffAt), false)) > 180) {
                continue;
            }

            $score = self::similarity($home, (string) ($event['home_team'] ?? '')) + self::similarity($away, (string) ($event['away_team'] ?? ''));

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $event;
            }
        }

        // Les deux équipes doivent ressembler (un nom exact compte 1).
        return $bestScore >= 1.2 ? $best : null;
    }

    private static function similarity(string $first, string $second): float
    {
        $a = self::normalize($first);
        $b = self::normalize($second);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        $ignored = ['fc', 'cf', 'sc', 'ac', 'afc', 'club', 'de', 'the', 'sporting', 'real', 'united', 'city'];
        $tokensA = array_values(array_diff(explode(' ', $a), $ignored));
        $tokensB = array_values(array_diff(explode(' ', $b), $ignored));

        if ($tokensA === [] || $tokensB === []) {
            return str_contains($a, $b) || str_contains($b, $a) ? 0.7 : 0.0;
        }

        $common = count(array_intersect($tokensA, $tokensB));

        if ($common === 0) {
            // Abréviation : « psg » / « paris saint germain », « wolves » / « wolverhampton ».
            $initialsA = implode('', array_map(fn ($token) => $token[0], $tokensA));
            $initialsB = implode('', array_map(fn ($token) => $token[0], $tokensB));

            if ($initialsA === implode('', $tokensB) || $initialsB === implode('', $tokensA)) {
                return 0.8;
            }

            foreach ($tokensA as $tokenA) {
                foreach ($tokensB as $tokenB) {
                    if (strlen($tokenA) >= 4 && strlen($tokenB) >= 4 && (str_starts_with($tokenA, substr($tokenB, 0, 4)) || str_starts_with($tokenB, substr($tokenA, 0, 4)))) {
                        return 0.6;
                    }
                }
            }

            return 0.0;
        }

        return 0.6 + 0.4 * ($common / max(count($tokensA), count($tokensB)));
    }

    private function sportKeyFor(array $match): ?string
    {
        if (! empty($match['odds_key'])) {
            return $match['odds_key'];
        }

        // Matchs sans clé (anciens tests, saisie manuelle) : par nom de championnat.
        $name = LeagueCatalog::normalize((string) ($match['league'] ?? ''));

        foreach (LeagueCatalog::LEAGUES as $key => [$label, , , $names]) {
            if ($name !== '' && (in_array($name, $names, true) || LeagueCatalog::normalize($label) === $name)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function teams(array $match): array
    {
        if (! empty($match['home']) && ! empty($match['away'])) {
            return [$match['home'], $match['away']];
        }

        $parts = preg_split('/\s+vs\s+/i', (string) ($match['match'] ?? ''));

        return count($parts) >= 2 ? [trim($parts[0]), trim($parts[1])] : [(string) ($match['match'] ?? ''), 'Adversaire'];
    }

    private static function line(float $point): string
    {
        return rtrim(rtrim(number_format($point, 2, ',', ''), '0'), ',');
    }

    private static function matchKey(string $home, string $away): string
    {
        return self::normalize($home) . '|' . self::normalize($away);
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = iconv('UTF-8', 'ASCII//TRANSLIT', $value) ?: $value;

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $value));
    }
}
