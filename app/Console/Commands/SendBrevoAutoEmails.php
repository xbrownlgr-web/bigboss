<?php

namespace App\Console\Commands;

use App\Services\BrevoAutomationService;
use Illuminate\Console\Command;

class SendBrevoAutoEmails extends Command
{
    protected $signature = 'bigboss:send-auto-emails';

    protected $description = 'Envoie la séquence quotidienne d’e-mails automatiques Brevo (relance J+1 et offre Pack VIP J+3).';

    public function handle(BrevoAutomationService $brevo): int
    {
        $counts = $brevo->runDailySequence();

        $this->info("E-mails auto Brevo envoyés : {$counts['welcome_j1']} relance(s) J+1, {$counts['pack_promo_j3']} offre(s) Pack VIP J+3.");

        return self::SUCCESS;
    }
}
