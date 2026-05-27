<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cronómetro activo (single-row). Solo puede existir uno en marcha;
 * el TimerController se encarga de la unicidad.
 */
class ActiveTimer extends Model
{
    public $timestamps = false;

    public const STATE_FOCUS       = 'focus';
    public const STATE_SHORT_BREAK = 'short_break';
    public const STATE_LONG_BREAK  = 'long_break';

    protected $fillable = [
        'task_id',
        'state',
        'cycle_count',
        'starts_at',
        'phase_started_at',
        'paused_at',
        'paused_offset_seconds',
        'created_at',
    ];

    protected $casts = [
        'task_id'               => 'integer',
        'cycle_count'           => 'integer',
        'starts_at'             => 'datetime',
        'phase_started_at'      => 'datetime',
        'paused_at'             => 'datetime',
        'paused_offset_seconds' => 'integer',
        'created_at'            => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function isFocus(): bool
    {
        return $this->state === self::STATE_FOCUS;
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null;
    }
}
