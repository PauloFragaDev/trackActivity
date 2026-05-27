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

    protected $fillable = ['task_id', 'starts_at', 'created_at'];

    protected $casts = [
        'task_id'    => 'integer',
        'starts_at'  => 'datetime',
        'created_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
