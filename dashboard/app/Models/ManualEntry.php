<?php

namespace App\Models;

use App\Enums\EntryKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tramo de tiempo añadido a mano por el usuario (reunión, corrección de
 * horas…). Capa independiente del tracking automático: el Aggregator no
 * la toca y su inicio/fin son arbitrarios.
 *
 * Convención del proyecto: starts_at / ends_at se guardan en UTC.
 */
class ManualEntry extends Model
{
    protected $fillable = [
        'starts_at', 'ends_at', 'project_id', 'task_id', 'kind', 'title', 'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'kind'      => EntryKind::class,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** Duración en minutos (mínimo 1). */
    public function durationMinutes(): int
    {
        return max(1, (int) $this->starts_at->diffInMinutes($this->ends_at));
    }

    /**
     * Entradas cuyo inicio cae dentro del rango UTC [$startUtc, $endUtc).
     */
    public function scopeStartingBetween($query, \DateTimeInterface $startUtc, \DateTimeInterface $endUtc)
    {
        return $query
            ->where('starts_at', '>=', $startUtc->format('Y-m-d H:i:s'))
            ->where('starts_at', '<',  $endUtc->format('Y-m-d H:i:s'));
    }
}
