<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Son d'un message vocal du support, supprimé 3 jours après (voir PurgeVoiceMessages).
 */
class MessageAudio extends Model
{
    public const RETENTION_DAYS = 3;

    // 2 minutes maximum, 4 Mo maximum.
    public const MAX_SECONDS = 120;

    public const MAX_KILOBYTES = 4096;

    // « audio » est invariable en anglais : Laravel devinerait « message_audio ».
    protected $table = 'message_audios';

    protected $fillable = ['message_id', 'mime', 'size', 'data'];

    protected $hidden = ['data'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
