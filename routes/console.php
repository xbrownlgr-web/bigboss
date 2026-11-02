<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
