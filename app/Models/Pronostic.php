<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pronostic extends Model
{
    use HasFactory;

    protected $fillable = [
        'niveau',
        'couleur',
        'cote_totale',
        'probabilite',
        'evenements',
        'code_1xbet',
        'code_melbet',
        'match_date',
        'match_time',
        'team_home',
        'team_away',
        'competition',
        'prediction',
        'odds',
        'confidence',
        'analysis',
        'is_vip',
        'published_at',
        'valid_until',
        'result_status',
        'result_note',
        'result_checked_at',
        'result_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'match_date' => 'date',
            'odds' => 'decimal:2',
            'cote_totale' => 'decimal:2',
            'confidence' => 'integer',
            'probabilite' => 'integer',
            'evenements' => 'array',
            'is_vip' => 'boolean',
            'published_at' => 'datetime',
            'valid_until' => 'datetime',
            'result_checked_at' => 'datetime',
            'result_notified_at' => 'datetime',
        ];
    }
}
