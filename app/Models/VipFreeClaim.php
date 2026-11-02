<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Demande de VIP Silver offert (1 mois) via parrainage 1xBet.
 * La capture d'écran est stockée en base64 dans la colonne `screenshot`.
 */
class VipFreeClaim extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'level',
        'promo_code',
        'account_id',
        'screenshot',
        'screenshot_mime',
        'status',
        'admin_note',
        'reviewed_by',
        'reviewed_at',
    ];

    // Ne jamais sérialiser la capture (lourde) dans les réponses JSON par défaut.
    protected $hidden = ['screenshot'];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
