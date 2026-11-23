<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Garde-fou léger de maintien de la base (1 fois par heure : TiDB Serverless ne coupe pas
// comme Aiven, inutile de solliciter le quota RU toutes les 3 minutes).
Schedule::call(function () {
    try {
        DB::select('SELECT 1');
    } catch (Throwable $exception) {
        Log::warning('Ping de maintien de la base en échec.', ['error' => $exception->getMessage()]);
    }
})->hourly()->name('db-keep-alive')->withoutOverlapping(2);

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

// Diffuse progressivement les notifications associées aux pronostics publiés (toutes les 5 min pour économiser les RU TiDB).
Schedule::command('binho:send-pronostic-notification')
    ->everyFiveMinutes()
    ->timezone('Africa/Porto-Novo')
    ->withoutOverlapping(4);

// Rappels d'expiration des abonnements VIP (3 jours avant, puis le jour de la fin).
Schedule::command('binho:vip-reminders')
    ->hourly()
    ->timezone('Africa/Porto-Novo')
    ->withoutOverlapping(10);

// Messages vocaux du support : le son est supprimé de la base 3 jours après l'envoi.
Schedule::command('bigboss:purge-voice-messages')
    ->hourly()
    ->withoutOverlapping(10);

// E-mails automatiques Brevo (relance J+1 et offre Pack VIP J+3, plafonnés pour respecter le quota gratuit).
Schedule::command('bigboss:send-auto-emails')
    ->dailyAt('10:00')
    ->timezone('Africa/Porto-Novo')
    ->withoutOverlapping(30);
