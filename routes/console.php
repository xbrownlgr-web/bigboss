<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Garde la base éveillée : les plans MySQL gratuits (Aiven) s'éteignent automatiquement après
// un moment d'inactivité — et une fois éteints, leur adresse disparaît d'Internet (plus aucune
// page ne fonctionne) jusqu'à ce qu'on les rallume à la main dans leur console. Le ping UptimeRobot
// sur /up ne suffit pas : cette route ne touche jamais la base. Une requête minuscule toutes les
// 3 minutes suffit à compter comme de l'activité pour Aiven.
Schedule::call(function () {
    try {
        DB::select('SELECT 1');
    } catch (Throwable $exception) {
        Log::warning('Ping de maintien de la base en échec.', ['error' => $exception->getMessage()]);
    }
})->everyThreeMinutes()->name('db-keep-alive')->withoutOverlapping(2);

// Chaque minute : génère les coupons des groupes dont l'heure de publication est arrivée
// (Starter, Bronze, Silver, Gold, Platinum ; heures dans Admin > Paramètres, heure du Bénin).
// Nécessite que le planificateur tourne : « php artisan schedule:run » chaque minute (voir docker/start.sh).
Schedule::command('binho:run-pronostic-slots')
    ->everyMinute()
    ->timezone('Africa/Porto-Novo')
    // Verrou de 30 min : une génération longue n'est jamais lancée deux fois.
    ->withoutOverlapping(30)
    // En arrière-plan : une génération de plusieurs minutes ne retarde pas les notifications
    // (une toutes les 2 minutes) ni les autres tâches de la minute.
    ->runInBackground();

// Toutes les heures : récupère les résultats des matchs, valide les combinés gagnants
// et notifie les utilisateurs des coupons gagnants (« Coupon VIP cote X est validé. Boomm »).
Schedule::command('binho:settle-pronostics')
    ->hourly()
    ->timezone('Africa/Porto-Novo')
    ->withoutOverlapping(10);

// Diffuse progressivement les notifications associées aux pronostics publiés.
Schedule::command('binho:send-pronostic-notification')
    ->everyMinute()
    ->timezone('Africa/Porto-Novo')
    ->withoutOverlapping(1);

// Rappels d'expiration des abonnements VIP (3 jours avant, puis le jour de la fin).
Schedule::command('binho:vip-reminders')
    ->hourly()
    ->timezone('Africa/Porto-Novo')
    ->withoutOverlapping(10);

// Messages vocaux du support : le son est supprimé de la base 3 jours après l'envoi.
Schedule::command('bigboss:purge-voice-messages')
    ->hourly()
    ->withoutOverlapping(10);
