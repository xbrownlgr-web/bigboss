<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\URL;

class Message extends Model
{
    use HasFactory;

    // Texte d'un message vocal : aperçus, alertes, et anciennes versions de l'app sans lecteur.
    public const VOICE_LABEL = '🎤 Message vocal';

    protected $fillable = [
        'user_id',
        'transaction_id',
        'sender',
        'content',
        'audio_duration',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'audio_duration' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function audio(): HasOne
    {
        return $this->hasOne(MessageAudio::class);
    }

    public function isVoice(): bool
    {
        return $this->audio_duration !== null;
    }

    /**
     * Le son est encore là (il est supprimé 3 jours après l'envoi).
     */
    public function hasAudio(): bool
    {
        return $this->isVoice() && ($this->relationLoaded('audio') ? $this->audio !== null : $this->audio()->exists());
    }

    /**
     * Message tel que l'application le reçoit : lien d'écoute signé et temporaire (le lecteur
     * audio du téléphone n'envoie pas le jeton de connexion), ou « supprimé » après 3 jours.
     */
    public function toAppArray(): array
    {
        $hasAudio = $this->hasAudio();

        return [
            ...Arr::except($this->toArray(), ['audio']),
            'audio_url' => $hasAudio ? URL::temporarySignedRoute('messages.audio', now()->addHours(6), ['message' => $this->id]) : null,
            'audio_expired' => $this->isVoice() && ! $hasAudio,
        ];
    }
}
