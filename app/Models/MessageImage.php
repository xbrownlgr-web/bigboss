<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Photo d'un message du support, supprimée 3 jours après (voir PurgeVoiceMessages).
 */
class MessageImage extends Model
{
    public const MAX_KILOBYTES = 6144;

    protected $table = 'message_images';

    protected $fillable = ['message_id', 'mime', 'size', 'data'];

    protected $hidden = ['data'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
