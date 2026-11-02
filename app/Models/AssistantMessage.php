<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantMessage extends Model
{
    public const ROLE_USER = 'user';

    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = [
        'user_id',
        'role',
        'content',
        'guides',
        'handoff',
    ];

    protected function casts(): array
    {
        return [
            'guides' => 'array',
            'handoff' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
