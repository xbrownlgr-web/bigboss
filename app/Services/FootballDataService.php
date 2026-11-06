<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Support\FootballDataLeagues;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Classement et forme récente via football-data.org (plan gratuit TIER_ONE), pour les championnats
 * qu'il couvre (voir FootballDataLeagues) : décharge Highlightly de ces requêtes, qui reste la seule
 * source pour les autres championnats ET pour le règlement des combinés (les identifiants de match
 * diffèrent d'un fournisseur à l'autre ; voir CLAUDE.md — ne jamais utiliser cette classe pour
 * déterminer un résultat gagné/perdu).
 *
 * Plan gratuit : 10 requêtes par minute, sans plafond quotidien (vérifié en direct le 2026-11-06,
 * en-têtes x-requests-available-minute / X-RequestCounter-Reset). Le classement n'a qu'un tableau
 * TOTAL (pas de domicile/extérieur séparé) : ValueAnalyzer bascule déjà automatiquement sur le total
 * quand le détail domicile/extérieur manque (voir expectedGoals()), donc aucune donnée n'est inventée.
 */
class FootballDataService
{
    private const BASE_URL = 'https://api.football-data.org/v4';

    private const CACHE_TTL_HOURS = 24;

    public function key(): ?string
    {
        return AppSetting::value('football_data_api_key') ?: null;
    }

    public function covers(?string $oddsKey): bool
    {
        return $this->key() !== null && FootballDataLeagues::competitionCode($oddsKey) !== null;
    }

    /**
     * @return array{teams: int, leader_points: int, relegation_points: ?int, home: ?array, away: ?array}|null
     */
    public function standings(string $oddsKey, int $homeId, int $awayId): ?array
    {
        $code = FootballDataLeagues::competitionCode($oddsKey);
        $key = $this->key();

        if ($code === null || $key === null) {
            return null;
        }

        $json = $this->get($key, "standings:{$code}", "/competitions/{$code}/standings");

        if ($json === null) {
            return null;
        }

        $table = collect($json['standings'] ?? [])
            ->first(fn ($group) => ($group['type'] ?? null) === 'TOTAL')['table'] ?? null;

        if (! is_array($table) || $table === []) {
            return null;
        }

        $rows = collect($table)->sortBy('position')->values();
        $home = $rows->first(fn ($row) => (int) ($row['team']['id'] ?? 0) === $homeId);
        $away = $rows->first(fn ($row) => (int) ($row['team']['id'] ?? 0) === $awayId);

        if (! $home || ! $away) {
            return null;
        }

        $teams = $rows->count();

        return [
            'teams' => $teams,
            'leader_points' => (int) ($rows->first()['points'] ?? 0),
            'relegation_points' => $teams >= 10 ? (int) ($rows->get($teams - 3)['points'] ?? 0) : null,
            // Pas de tableau domicile/extérieur séparé sur le plan gratuit : seul « total » est
            // rempli, ValueAnalyzer bascule déjà dessus quand le détail domicile/extérieur manque.
            'home' => $this->standingRow($home),
            'away' => $this->standingRow($away),
        ];
    }

    private function standingRow(array $row): array
    {
        return [
            'position' => (int) ($row['position'] ?? 0),
            'points' => (int) ($row['points'] ?? 0),
            'total' => [
                'games' => (int) ($row['playedGames'] ?? 0),
                'wins' => (int) ($row['won'] ?? 0),
                'draws' => (int) ($row['draw'] ?? 0),
                'loses' => (int) ($row['lost'] ?? 0),
                'scored' => (int) ($row['goalsFor'] ?? 0),
                'received' => (int) ($row['goalsAgainst'] ?? 0),
            ],
        ];
    }

    /**
     * 5 derniers matchs terminés d'une équipe (toutes compétitions confondues, comme Highlightly).
     *
     * @return array<int, array{date: string, opponent: string, venue: string, gf: int, ga: int, result: string}>|null
     */
    public function recentForm(int $teamId): ?array
    {
        $key = $this->key();

        if ($key === null || $teamId === 0) {
            return null;
        }

        $json = $this->get($key, "form:{$teamId}", "/teams/{$teamId}/matches", ['status' => 'FINISHED', 'limit' => 5]);
        $matches = $json['matches'] ?? null;

        if (! is_array($matches)) {
            return null;
        }

        $games = [];

        foreach ($matches as $match) {
            $home = $match['score']['fullTime']['home'] ?? null;
            $away = $match['score']['fullTime']['away'] ?? null;

            if ($home === null || $away === null) {
                continue;
            }

            $isHome = (int) ($match['homeTeam']['id'] ?? 0) === $teamId;
            $gf = $isHome ? (int) $home : (int) $away;
            $ga = $isHome ? (int) $away : (int) $home;

            $games[] = [
                'date' => substr((string) ($match['utcDate'] ?? ''), 0, 10),
                'opponent' => (string) ($isHome ? ($match['awayTeam']['name'] ?? '') : ($match['homeTeam']['name'] ?? '')),
                'venue' => $isHome ? 'dom' : 'ext',
                'gf' => $gf,
                'ga' => $ga,
                'result' => $gf > $ga ? 'V' : ($gf === $ga ? 'N' : 'D'),
            ];
        }

        usort($games, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return array_slice($games, 0, 5);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>|null
     */
    private function get(string $key, string $cacheKey, string $path, array $query = []): ?array
    {
        $cached = Cache::get('football_data:' . $cacheKey);

        if (is_array($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(15)
                ->withHeaders(['X-Auth-Token' => $key])
                ->get(self::BASE_URL . $path, $query);
        } catch (Throwable $exception) {
            Log::warning('football-data.org injoignable.', ['request' => $cacheKey, 'error' => $exception->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            // 429 = quota minute dépassé (10/min) : Highlightly reste en secours pour ce match.
            Log::warning('football-data.org a refusé la demande.', ['request' => $cacheKey, 'status' => $response->status()]);

            return null;
        }

        $json = $response->json();

        if (! is_array($json)) {
            return null;
        }

        Cache::put('football_data:' . $cacheKey, $json, now()->addHours(self::CACHE_TTL_HOURS));

        return $json;
    }
}
