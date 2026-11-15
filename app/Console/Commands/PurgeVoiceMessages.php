<?php

namespace App\Console\Commands;

use App\Models\MessageAudio;
use App\Models\MessageImage;
use Illuminate\Console\Command;

/**
 * Les messages vocaux du support sont supprimés de la base 3 jours après leur envoi (demande du
 * fondateur). Le message reste dans la conversation, marqué « vocal supprimé ».
 */
class PurgeVoiceMessages extends Command
{
    protected $signature = 'bigboss:purge-voice-messages';

    protected $description = 'Supprime le son des vocaux et les photos du support de plus de 3 jours';

    public function handle(): int
    {
        $deleted = MessageAudio::query()
            ->where('created_at', '<', now()->subDays(MessageAudio::RETENTION_DAYS))
            ->delete();

        // Les photos du support suivent la même règle (demande du fondateur, 2026-11-15).
        $photos = MessageImage::query()
            ->where('created_at', '<', now()->subDays(MessageAudio::RETENTION_DAYS))
            ->delete();

        $this->info("{$deleted} message(s) vocal(aux) et {$photos} photo(s) supprimé(s).");

        return self::SUCCESS;
    }
}
