<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Support\LeagueCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Matchs réels via l'API Football de Highlightly (https://highlightly.net).
 * Toutes les pages d'une journée sont lues (pas seulement les 100 premiers matchs) : la recherche
 * couvre tous les championnats avant de conclure qu'il n'y a rien à analyser.
 */
class FixtureService
{
    // Base URL directe Highlightly (Football API). Via RapidAPI : https://football-highlights-api.p.rapidapi.com
    // (+ header 'x-rapidapi-host' => 'football-highlights-api.p.rapidapi.com').
    private const ENDPOINT = 'https://soccer.highlightly.net/matches';

    private const PAGE_SIZE = 100;

    // Quota gratuit Highlightly : 100 requêtes par jour. Une journée chargée tient en 5 pages.
    private const MAX_PAGES = 5;

    // La liste des matchs d'une date est gardée 6 h : tous les créneaux de la journée la partagent.
    private const FIXTURES_TTL_MINUTES = 360;

    // Un combiné n'est validé/perdu automatiquement que sur un match réellement terminé en temps réglementaire.
    // Les prolongations / tirs au but (coupe) sont laissés à l'admin : les marchés 1X2 se règlent sur 90 min.
    private const SETTLED_STATES = ['Finished'];

    /**
     * Matchs pas encore commencés entre deux instants (tous championnats ayant des cotes réelles),
     * les ligues prioritaires d'abord, puis par heure de coup d'envoi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function upcoming(Carbon $from, Carbon $to, int $limit = 200): array
    {
        $timezone = $this->timezone();
        $dates = [];

        for ($day = $from->copy()->setTimezone($timezone)->startOfDay(); $day->lte($to->copy()->setTimezone($timezone)); $day->addDay()) {
            $dates[] = $day->toDateString();
        }

        return collect($dates)
            ->flatMap(fn ($date) => $this->matchesOn($date))
            ->filter(fn ($item) => ($item['state']['description'] ?? '') === 'Not started')
            ->filter(function ($item) use ($from, $to) {
                $kickoff = isset($item['date']) ? Carbon::parse($item['date']) : null;

                return $kickoff && $kickoff->gte($from) && $kickoff->lte($to);
            })
            ->map(fn ($item) => $this->fixture($item, $timezone))
            ->filter()
            ->unique('id')
            ->sortBy([['priority', 'asc'], ['kickoff_at', 'asc']])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Matchs d'une journée entière (génération manuelle depuis l'admin).
     *
     * @return array<int, array<string, mixed>>
     */
    public function forDate(Carbon $date, int $limit = 200): array
    {
        $day = Carbon::parse($date->toDateString(), $this->timezone());

        return $this->upcoming($day->copy()->startOfDay(), $day->copy()->endOfDay(), $limit);
    }

    /**
     * Résultats des matchs des combinés en attente : une requête par championnat et par date
     * (au lieu de toute la journée), gardée 45 min.
     *
     * @param array<int, array<string, mixed>> $events sélections des combinés (fixture_id, league_id, kickoff_at)
     * @param string $fallbackDate date du combiné, pour les anciennes sélections sans date
     * @return array<int, array{home: string, away: string, home_goals: ?int, away_goals: ?int, finished: bool, state: string}>
     */
    public function resultsForEvents(array $events, string $fallbackDate): array
    {
        $timezone = $this->timezone();
        $groups = [];

        foreach ($events as $event) {
            if (empty($event['fixture_id']) || ($event['sport'] ?? 'football') !== 'football') {
                continue;
            }

            $date = ! empty($event['kickoff_at'])
                ? Carbon::parse($event['kickoff_at'])->setTimezone($timezone)->toDateString()
                : $fallbackDate;
            $groups[$date . '|' . ($event['league_id'] ?? '')] = [$date, $event['league_id'] ?? null];
        }

        $results = [];

        foreach ($groups as [$date, $leagueId]) {
            $cacheKey = 'highlightly:results:' . $date . ':' . ($leagueId ?: 'all');
            $items = Cache::get($cacheKey);

            if (! is_array($items)) {
                $items = $leagueId
                    ? $this->fetch(['date' => $date, 'leagueId' => $leagueId, 'limit' => self::PAGE_SIZE])
                    : $this->allPages($date);
                Cache::put($cacheKey, $items, now()->addMinutes(45));
            }

            foreach ($items as $item) {
                $id = (int) ($item['id'] ?? 0);

                if ($id === 0) {
                    continue;
                }

                $state = (string) ($item['state']['description'] ?? '');
                [$homeGoals, $awayGoals] = self::parseScore((string) ($item['state']['score']['current'] ?? ''));

                $results[$id] = [
                    'home' => (string) ($item['homeTeam']['name'] ?? ''),
                    'away' => (string) ($item['awayTeam']['name'] ?? ''),
                    'home_goals' => $homeGoals,
                    'away_goals' => $awayGoals,
                    'finished' => in_array($state, self::SETTLED_STATES, true),
                    'state' => $state,
                ];
            }
        }

        return $results;
    }

    /**
     * Résultats de toute une journée (anciens combinés sans championnat enregistré).
     *
     * @return array<int, array{home: string, away: string, home_goals: ?int, away_goals: ?int, finished: bool, state: string}>
     */
    public function resultsForDate(Carbon $date): array
    {
        return $this->resultsForEvents([['fixture_id' => -1, 'sport' => 'football']], $date->toDateString());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matchesOn(string $date): array
    {
        $cacheKey = 'highlightly:fixtures:' . $date;
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $items = $this->allPages($date);
        } catch (RuntimeException $exception) {
            // Quota du jour atteint ou API en panne : la dernière liste connue (24 h) sert encore.
            $stale = Cache::get($cacheKey . ':stale');

            if (is_array($stale)) {
                return $stale;
            }

            throw $exception;
        }

        Cache::put($cacheKey, $items, now()->addMinutes(self::FIXTURES_TTL_MINUTES));
        Cache::put($cacheKey . ':stale', $items, now()->addHours(24));

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function allPages(string $date): array
    {
        $all = [];

        for ($page = 0; $page < self::MAX_PAGES; $page++) {
            $items = $this->fetch(['date' => $date, 'limit' => self::PAGE_SIZE, 'offset' => $page * self::PAGE_SIZE]);
            $all = [...$all, ...$items];

            if (count($items) < self::PAGE_SIZE) {
                break;
            }
        }

        return $all;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<int, array<string, mixed>>
     */
    private function fetch(array $query): array
    {
        $key = AppSetting::value('highlightly_api_key');

        if (! $key) {
            throw new RuntimeException('Clé Highlightly manquante (Admin > Paramètres > Intelligence artificielle).');
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders(['x-rapidapi-key' => $key])
                ->get(self::ENDPOINT, [...$query, 'timezone' => $this->timezone()]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Highlightly injoignable : ' . $exception->getMessage());
        }

        if (! $response->successful()) {
            throw new RuntimeException('Highlightly a refusé la demande : erreur HTTP ' . $response->status());
        }

        return $response->json('data') ?? [];
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>|null
     */
    private function fixture(array $item, string $timezone): ?array
    {
        $league = LeagueCatalog::resolve($item);

        if ($league === null || empty($item['homeTeam']['name']) || empty($item['awayTeam']['name'])) {
            return null;
        }

        return [
            'id' => $item['id'] ?? null,
            'sport' => 'football',
            'match' => $item['homeTeam']['name'] . ' vs ' . $item['awayTeam']['name'],
            'competition' => $league['label'],
            'kickoff' => Carbon::parse($item['date'])->setTimezone($timezone)->format('H:i'),
            'odds_key' => $league['odds_key'],
            'priority' => $league['priority'],
            'home' => $item['homeTeam']['name'],
            'away' => $item['awayTeam']['name'],
            // Identifiants Highlightly : servent à collecter forme, classement et H2H avant l'analyse.
            'home_id' => $item['homeTeam']['id'] ?? null,
            'away_id' => $item['awayTeam']['id'] ?? null,
            'league_id' => $item['league']['id'] ?? null,
            'season' => $item['league']['season'] ?? null,
            'kickoff_at' => $item['date'] ?? null,
        ];
    }

    private function timezone(): string
    {
        $timezone = config('app.timezone', 'UTC');

        return $timezone === 'UTC' ? 'Etc/UTC' : $timezone;
    }

    /**
     * Le score Highlightly est une chaîne « 3 - 1 » (domicile - extérieur) ; pas de champ numérique.
     *
     * @return array{0: ?int, 1: ?int}
     */
    private static function parseScore(string $current): array
    {
        if (preg_match('/(\d+)\s*-\s*(\d+)/', $current, $match)) {
            return [(int) $match[1], (int) $match[2]];
        }

        return [null, null];
    }

    /**
     * Matchs saisis à la main : « Équipe A vs Équipe B | Compétition | 20:00 », un par ligne.
     *
     * @return array<int, array{match: string, competition: string, kickoff: string}>
     */
    public function parseManual(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];

        return collect($lines)
            ->map(fn ($line) => array_map('trim', explode('|', $line)))
            ->filter(fn ($parts) => ($parts[0] ?? '') !== '' && preg_match('/\s+vs\.?\s+/i', $parts[0]))
            ->map(fn ($parts) => [
                'match' => preg_replace('/\s+vs\.?\s+/i', ' vs ', $parts[0]),
                'competition' => $parts[1] ?? '',
                'kickoff' => $parts[2] ?? '',
            ])
            ->take(40)
            ->values()
            ->all();
    }
}
