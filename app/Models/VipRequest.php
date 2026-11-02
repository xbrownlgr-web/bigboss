<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VipRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVATED = 'activated';

    protected $fillable = [
        'user_id',
        'level',
        'key',
        'price',
        'status',
        'expires_at',
        'activated_at',
        'activated_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'expires_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Clés encore activables : en attente et pas expirées.
     */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)->where('expires_at', '>', now());
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_PENDING && $this->expires_at->isFuture();
    }
}
