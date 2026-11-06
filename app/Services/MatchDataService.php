<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Collecte des données AVANT toute analyse, via Highlightly (même clé que les matchs du jour) :
 * classement (domicile / extérieur séparés), 5 derniers matchs de chaque équipe, H2H,
 * arbitre, stade, météo, probabilités du modèle Highlightly et actualités du match.
 *
 * Ce que l'API ne fournit pas (xG/xGA, blessés, compositions avant 30 min du coup d'envoi)
 * est marqué « donnée manquante » : l'analyse baisse alors la confiance au lieu de supposer.
 *
 * Offre gratuite Highlightly : 100 requêtes par jour, dont 24 pour la vérification horaire des
 * résultats. Une génération coûte au plus ~33 requêtes (8 matchs : 1 classement par compétition +
 * 3 requêtes par match) ; chaque réponse est gardée 24 h en cache, si bien que tous les créneaux
 * de la journée réutilisent les données collectées au premier (le classement et la forme ne
 * bougent pas d'un créneau à l'autre).
 */
class MatchDataService
{
    private const BASE_URL = 'https://soccer.highlightly.net';

    private const CACHE_TTL_HOURS = 24;

    // Au plus 8 matchs analysés par génération : 1 classement par compétition + 3 requêtes par match.
    public const MAX_MATCHES = 8;

    // Compétitions à part : rotation, aller-retour, sélections nationales peu stables.
    private const CUP_LEAGUES = [2486, 3337, 722432, 10996, 17804];

    private const NATIONAL_TEAM_LEAGUES = [5039, 5890, 1635];

    public function __construct(private readonly FootballDataService $footballData)
    {
    }

    /**
     * @param array<int, array<string, mixed>> $fixtures matchs déjà munis de leurs cotes (OddsService)
     * @return array<int, array<string, mixed>> les matchs analysables, chacun avec une clé « data »
     */
    public function enrich(array $fixtures): array
    {
        $key = AppSetting::value('highlightly_api_key');

        // Sans cote réelle, aucune valeur ne peut être calculée : ces matchs ne sont pas analysés.
        $fixtures = collect($fixtures)
            ->filter(fn ($fixture) => ! empty($fixture['selections']))
            ->take(self::MAX_MATCHES)
            ->values()
            ->all();

        if (! $key || $fixtures === []) {
            return array_map(fn ($fixture) => [...$fixture, 'data' => $this->emptyData($fixture)], $fixtures);
        }

        $requests = [];

        foreach ($fixtures as $fixture) {
            // Championnat couvert par football-data.org (plan gratuit) et clé réglée : classement et
            // forme viennent de là, ça décharge d'autant le quota Highlightly (voir FootballDataService).
            $onFootballData = $this->footballData->covers($fixture['odds_key'] ?? null);

            if (! $onFootballData && ! empty($fixture['league_id']) && ! empty($fixture['season'])) {
                $requests["standings:{$fixture['league_id']}:{$fixture['season']}"] = ['/standings', ['leagueId' => $fixture['league_id'], 'season' => $fixture['season']]];
            }

            if (! $onFootballData) {
                foreach (['home_id', 'away_id'] as $side) {
                    if (! empty($fixture[$side])) {
                        $requests["last5:{$fixture[$side]}"] = ['/last-five-games', ['teamId' => $fixture[$side]]];
                    }
                }
            }

            // H2H (historique des confrontations) retiré : poids faible dans le dossier et absent
            // du calcul des probabilités (ValueAnalyzer n'utilise que marché, Poisson, Highlightly).
            // L'économiser garde le quota Highlightly pour le classement, la forme et le modèle.

            if (! empty($fixture['id'])) {
                $requests["match:{$fixture['id']}"] = ["/matches/{$fixture['id']}", []];
            }
        }

        $responses = $this->fetchMany($key, $requests);

        return array_map(function (array $fixture) use ($responses) {
            $homeId = (int) ($fixture['home_id'] ?? 0);
            $awayId = (int) ($fixture['away_id'] ?? 0);
            $details = $responses['match:' . ($fixture['id'] ?? '')] ?? null;
            $onFootballData = $this->footballData->covers($fixture['odds_key'] ?? null);

            if ($onFootballData) {
                $standings = $this->footballData->standings((string) $fixture['odds_key'], $homeId, $awayId);
                $form = [
                    'home' => $this->footballData->recentForm($homeId),
                    'away' => $this->footballData->recentForm($awayId),
                ];
            } else {
                $rawStandings = $responses['standings:' . ($fixture['league_id'] ?? '') . ':' . ($fixture['season'] ?? '')] ?? null;
                $standings = $rawStandings !== null ? $this->standings($rawStandings, $fixture) : null;
                $form = [
                    'home' => $this->form($responses["last5:{$homeId}"] ?? null, $homeId),
                    'away' => $this->form($responses["last5:{$awayId}"] ?? null, $awayId),
                ];
            }

            return [...$fixture, 'data' => [
                'type' => $this->competitionType((int) ($fixture['league_id'] ?? 0)),
                'standings' => $standings,
                'form' => $form,
                'h2h' => null,
                'details' => $details !== null ? $this->details($details) : null,
                'collected_at' => now()->toIso8601String(),
            ]];
        }, $fixtures);
    }

    /**
     * Requêtes en parallèle, avec cache. Une requête en échec renvoie null (« donnée manquante »)
     * et n'est pas mise en cache, pour être retentée à la génération suivante.
     *
     * @param array<string, array{0: string, 1: array}> $requests
     * @return array<string, mixed>
     */
    private function fetchMany(string $key, array $requests): array
    {
        $results = [];
        $missing = [];

        foreach ($requests as $cacheKey => $request) {
            $cached = Cache::get('highlightly:' . $cacheKey);

            if ($cached !== null) {
                $results[$cacheKey] = $cached;
            } else {
                $missing[$cacheKey] = $request;
            }
        }

        if ($missing === []) {
            return $results;
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($missing, $key) {
                foreach ($missing as $cacheKey => [$path, $query]) {
                    $pool->as($cacheKey)
                        ->timeout(15)
                        ->withHeaders(['x-rapidapi-key' => $key])
                        ->get(self::BASE_URL . $path, $query);
                }
            });
        } catch (Throwable $exception) {
            Log::warning('Highlightly injoignable pendant la collecte des données.', ['error' => $exception->getMessage()]);

            return $results;
        }

        foreach ($missing as $cacheKey => $request) {
            $response = $responses[$cacheKey] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                Log::warning('Donnée Highlightly indisponible.', [
                    'request' => $cacheKey,
                    'status' => $response instanceof Response ? $response->status() : null,
                ]);

                continue;
            }

            $json = $response->json();
            $results[$cacheKey] = $json ?? [];
            Cache::put('highlightly:' . $cacheKey, $results[$cacheKey], now()->addHours(self::CACHE_TTL_HOURS));
        }

        return $results;
    }

    private function competitionType(int $leagueId): string
    {
        return match (true) {
            in_array($leagueId, self::NATIONAL_TEAM_LEAGUES, true) => 'selections',
            in_array($leagueId, self::CUP_LEAGUES, true) => 'coupe',
            default => 'championnat',
        };
    }

    /**
     * Position, points et bilans domicile / extérieur des deux équipes, plus les repères d'enjeu.
     */
    private function standings(mixed $json, array $fixture): ?array
    {
        $groups = is_array($json) ? ($json['groups'] ?? $json[0]['groups'] ?? []) : [];

        foreach ($groups as $group) {
            $rows = collect($group['standings'] ?? [])->sortBy('position')->values();
            $home = $rows->first(fn ($row) => (int) ($row['team']['id'] ?? 0) === (int) ($fixture['home_id'] ?? -1));
            $away = $rows->first(fn ($row) => (int) ($row['team']['id'] ?? 0) === (int) ($fixture['away_id'] ?? -1));

            if (! $home || ! $away) {
                continue;
            }

            $teams = $rows->count();

            return [
                'teams' => $teams,
                'leader_points' => (int) ($rows->first()['points'] ?? 0),
                // Points de la première équipe relégable (antépénultième place) : repère de l'enjeu « maintien ».
                'relegation_points' => $teams >= 10 ? (int) ($rows->get($teams - 3)['points'] ?? 0) : null,
                'home' => $this->standingRow($home),
                'away' => $this->standingRow($away),
            ];
        }

        return null;
    }

    private function standingRow(array $row): array
    {
        $split = fn (array $data) => [
            'games' => (int) ($data['games'] ?? 0),
            'wins' => (int) ($data['wins'] ?? 0),
            'draws' => (int) ($data['draws'] ?? 0),
            'loses' => (int) ($data['loses'] ?? 0),
            'scored' => (int) ($data['scoredGoals'] ?? 0),
            'received' => (int) ($data['receivedGoals'] ?? 0),
        ];

        return [
            'position' => (int) ($row['position'] ?? 0),
            'points' => (int) ($row['points'] ?? 0),
            'total' => $split($row['total'] ?? []),
            'home' => $split($row['home'] ?? []),
            'away' => $split($row['away'] ?? []),
        ];
    }

    /**
     * 5 derniers matchs terminés d'une équipe, du point de vue de cette équipe.
     *
     * @return array<int, array{date: string, opponent: string, venue: string, gf: int, ga: int, result: string}>|null
     */
    private function form(mixed $json, int $teamId): ?array
    {
        if (! is_array($json) || $teamId === 0) {
            return null;
        }

        $games = [];

        foreach ($this->rows($json) as $game) {
            [$homeGoals, $awayGoals] = $this->score($game);

            if ($homeGoals === null) {
                continue;
            }

            $isHome = (int) ($game['homeTeam']['id'] ?? 0) === $teamId;
            $gf = $isHome ? $homeGoals : $awayGoals;
            $ga = $isHome ? $awayGoals : $homeGoals;

            $games[] = [
                'date' => substr((string) ($game['date'] ?? ''), 0, 10),
                'opponent' => (string) ($isHome ? ($game['awayTeam']['name'] ?? '') : ($game['homeTeam']['name'] ?? '')),
                'venue' => $isHome ? 'dom' : 'ext',
                'gf' => $gf,
                'ga' => $ga,
                'result' => $gf > $ga ? 'V' : ($gf === $ga ? 'N' : 'D'),
            ];
        }

        usort($games, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return array_slice($games, 0, 5);
    }

    private function details(mixed $json): ?array
    {
        $match = is_array($json) && array_is_list($json) ? ($json[0] ?? null) : $json;

        if (! is_array($match)) {
            return null;
        }

        $prediction = collect($match['predictions']['prematch'] ?? [])
            ->sortByDesc(fn ($item) => (string) ($item['generatedAt'] ?? ''))
            ->first();

        $probabilities = null;
        if (is_array($prediction['probabilities'] ?? null)) {
            $probabilities = array_map(
                fn ($value) => $this->percentage($value),
                array_intersect_key($prediction['probabilities'], array_flip(['home', 'draw', 'away'])),
            );

            if (in_array(null, $probabilities, true) || count($probabilities) !== 3) {
                $probabilities = null;
            }
        }

        // Actualités de moins de 48 h seulement : au-delà, l'information est jugée périmée.
        $news = collect($match['news'] ?? [])
            ->filter(function ($item) {
                try {
                    return ! empty($item['title']) && Carbon::parse($item['datePublished'] ?? '')->greaterThan(now()->subHours(48));
                } catch (Throwable) {
                    return false;
                }
            })
            ->take(3)
            ->map(fn ($item) => ['title' => (string) $item['title'], 'date' => substr((string) $item['datePublished'], 0, 10)])
            ->values()
            ->all();

        return [
            'referee' => $match['referee']['name'] ?? null,
            'venue' => trim(($match['venue']['name'] ?? '') . (isset($match['venue']['city']) ? ', ' . $match['venue']['city'] : '')) ?: null,
            'forecast' => isset($match['forecast']['status'])
                ? trim($match['forecast']['status'] . ' ' . ($match['forecast']['temperature'] ?? ''))
                : null,
            'predictions' => $probabilities,
            'news' => $news,
        ];
    }

    private function percentage(mixed $value): ?float
    {
        if (! is_numeric(str_replace('%', '', (string) $value))) {
            return null;
        }

        $number = (float) str_replace('%', '', (string) $value);

        return $number > 1 ? $number / 100 : $number;
    }

    private function rows(array $json): array
    {
        return array_is_list($json) ? $json : ($json['data'] ?? []);
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function score(array $game): array
    {
        if (preg_match('/(\d+)\s*-\s*(\d+)/', (string) ($game['state']['score']['current'] ?? ''), $match)) {
            return [(int) $match[1], (int) $match[2]];
        }

        return [null, null];
    }

    private function emptyData(array $fixture): array
    {
        return [
            'type' => $this->competitionType((int) ($fixture['league_id'] ?? 0)),
            'standings' => null,
            'form' => ['home' => null, 'away' => null],
            'h2h' => null,
            'details' => null,
            'collected_at' => now()->toIso8601String(),
        ];
    }
}
