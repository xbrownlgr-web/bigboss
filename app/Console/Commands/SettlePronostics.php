<?php

namespace App\Console\Commands;

use App\Models\Pronostic;
use App\Services\ExpoPushService;
use App\Services\FixtureService;
use App\Services\OddsService;
use App\Services\PronosticSettlementService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Récupère les résultats réels des matchs (Highlightly), valide les combinés gagnants
 * et notifie les utilisateurs (« Coupon VIP cote X est validé. Boomm »).
 * Lancé automatiquement toutes les heures (voir routes/console.php).
 */
class SettlePronostics extends Command
{
    protected $signature = 'binho:settle-pronostics {--date= : Limiter à une date de matchs (YYYY-MM-DD).}';

    protected $description = 'Valide les combinés dont les matchs sont terminés et notifie les coupons gagnants.';

    public function __construct(
        private readonly FixtureService $fixtures,
        private readonly PronosticSettlementService $settlement,
        private readonly ExpoPushService $push,
        private readonly OddsService $odds,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Pronostic::query()
            ->whereNull('result_status')
            ->whereNotNull('match_date');

        if ($this->option('date')) {
            $query->whereDate('match_date', Carbon::parse($this->option('date'))->toDateString());
        } else {
            // Inutile de s'acharner sur de vieux combinés jamais terminés : fenêtre de 3 jours.
            $query->whereDate('match_date', '>=', Carbon::today()->subDays(3)->toDateString())
                ->whereDate('match_date', '<=', Carbon::today()->toDateString());
        }

        $pending = $query->orderBy('match_date')->get();

        if ($pending->isEmpty()) {
            $this->info('Aucun combiné en attente de résultat.');

            return self::SUCCESS;
        }

        $settled = 0;
        $won = 0;
        $notified = 0;

        foreach ($pending as $pronostic) {
            $events = array_values(array_filter($pronostic->evenements ?? [], 'is_array'));

            // Rien à demander tant qu'aucun match n'a pu se terminer (coup d'envoi + 1 h 50).
            $finishedSoon = collect($events)->contains(fn ($event) => empty($event['kickoff_at'])
                || Carbon::parse($event['kickoff_at'])->addMinutes(110)->isPast());

            if (! $finishedSoon) {
                continue;
            }

            $verdict = $this->settlement->settle($pronostic, $this->resultsFor($events, $pronostic->match_date->toDateString()));

            if ($verdict === null) {
                continue;
            }

            $pronostic->update(['result_status' => $verdict, 'result_checked_at' => now()]);
            $settled++;

            if ($verdict === 'won' && ! $pronostic->result_notified_at) {
                $notified += $this->notifyWon($pronostic);
                $pronostic->update(['result_notified_at' => now()]);
                $won++;
            }
        }

        $this->info("{$settled} combiné(s) réglé(s), {$won} gagnant(s), {$notified} notification(s) envoyée(s).");

        return self::SUCCESS;
    }

    /**
     * Résultats football (Highlightly, par championnat et date de chaque match) et basket /
     * handball (scores finaux de The Odds API), indexés par identifiant de match.
     *
     * @param array<int, array<string, mixed>> $events
     * @return array<int|string, array<string, mixed>>
     */
    private function resultsFor(array $events, string $date): array
    {
        $results = [];

        try {
            $results = $this->fixtures->resultsForEvents($events, $date);
        } catch (Throwable $exception) {
            Log::warning('Résultats football indisponibles pour la validation des combinés.', [
                'date' => $date,
                'error' => $exception->getMessage(),
            ]);
        }

        $sports = collect($events)
            ->filter(fn ($event) => ($event['sport'] ?? 'football') !== 'football' && ! empty($event['odds_key']))
            ->pluck('odds_key')
            ->unique();

        foreach ($sports as $sport) {
            foreach ($this->odds->scores($sport) as $eventId => $score) {
                $results[$eventId] = [
                    'home_goals' => $score['home_score'],
                    'away_goals' => $score['away_score'],
                    'finished' => $score['completed'],
                    'state' => $score['completed'] ? 'Finished' : 'In progress',
                ];
            }
        }

        return $results;
    }

    private function notifyWon(Pronostic $pronostic): int
    {
        $niveau = $pronostic->niveau ?: 'Combiné';
        $vip = $pronostic->is_vip ? 'VIP ' : '';
        $odd = number_format((float) ($pronostic->cote_totale ?? $pronostic->odds), 2, ',', ' ');

        return $this->push->sendToActiveUsers(
            "✅ Coupon {$vip}{$niveau} validé !",
            "🏆 Le coupon {$vip}{$niveau} cote {$odd} est validé. Boomm 💥",
            [
                'type' => 'pronostic',
                'event' => 'won',
                'pronostic_id' => $pronostic->id,
                'niveau' => $niveau,
            ],
        );
    }
}
