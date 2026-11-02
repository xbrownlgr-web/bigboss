<?php

namespace App\Console\Commands;

use App\Models\PronosticRun;
use App\Support\PronosticRules;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Lancé chaque minute : déclenche la génération des groupes dont l'heure est arrivée
 * (Admin > Paramètres > heures de publication). Un créneau ne part qu'une fois par jour, et
 * reste rattrapable 20 minutes (serveur qui redémarre, génération précédente longue).
 */
class RunPronosticSlots extends Command
{
    protected $signature = 'binho:run-pronostic-slots';

    protected $description = 'Génère les coupons des groupes dont l’heure de publication est arrivée.';

    private const CATCH_UP_MINUTES = 20;

    public function handle(): int
    {
        $timezone = PronosticRules::TIMEZONE;
        $now = now()->setTimezone($timezone);
        $started = 0;

        foreach (PronosticRules::schedule() as $level => $times) {
            foreach ($times as $time) {
                $slot = Carbon::parse($now->toDateString() . ' ' . $time, $timezone);

                if ($now->lt($slot) || $slot->diffInMinutes($now) > self::CATCH_UP_MINUTES) {
                    continue;
                }

                // Le créneau est réservé avant de générer : deux passages simultanés n'en lancent qu'un.
                $run = PronosticRun::query()->firstOrCreate(
                    ['run_date' => $now->toDateString(), 'level' => $level, 'slot' => $time],
                    ['status' => PronosticRun::STATUS_RUNNING],
                );

                if (! $run->wasRecentlyCreated) {
                    continue;
                }

                $this->info("Créneau {$time} : {$level}");
                $this->call('binho:generate-pronostics', ['--level' => [$level], '--slot' => $time]);
                $started++;
            }
        }

        if ($started === 0) {
            $this->line('Aucun créneau à cette minute.');
        }

        return self::SUCCESS;
    }
}
