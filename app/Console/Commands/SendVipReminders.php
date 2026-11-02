<?php

namespace App\Console\Commands;

use App\Services\VipService;
use Illuminate\Console\Command;

class SendVipReminders extends Command
{
    protected $signature = 'binho:vip-reminders';

    protected $description = 'Prévient les abonnés VIP 3 jours avant la fin de leur abonnement, puis le jour de la fin.';

    public function handle(VipService $vip): int
    {
        $sent = $vip->sendReminders();

        $this->info("{$sent['soon']} rappel(s) « bientôt terminé », {$sent['ended']} rappel(s) « terminé ».");

        return self::SUCCESS;
    }
}
