<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace d'un créneau de génération (un niveau à une heure donnée) : affichée dans Admin > Pronostics.
 * run_date reste une chaîne « AAAA-MM-JJ » : comparée telle quelle, quel que soit le moteur SQL.
 */
class PronosticRun extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_EMPTY = 'empty';

    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'run_date',
        'level',
        'slot',
        'status',
        'message',
        'pronostic_id',
    ];

    public function pronostic(): BelongsTo
    {
        return $this->belongsTo(Pronostic::class);
    }
}
