<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VipSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'level',
        'starts_at',
        'ends_at',
        'reminded_soon_at',
        'reminded_ended_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminded_soon_at' => 'datetime',
            'reminded_ended_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('ends_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->ends_at->isFuture();
    }
}
