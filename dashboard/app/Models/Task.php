<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarea del tablero Kanban. `completed_at` se sincroniza con el estado Done.
 */
class Task extends Model
{
    protected $fillable = [
        'project_id', 'title', 'description', 'status', 'priority',
        'due_date', 'position', 'completed_at',
    ];

    /** Defaults en memoria (coinciden con los de la migración). */
    protected $attributes = [
        'status'   => 'todo',
        'position' => 0,
    ];

    protected $casts = [
        'project_id'   => 'integer',
        'status'       => TaskStatus::class,
        'priority'     => TaskPriority::class,
        'due_date'     => 'date',
        'position'     => 'integer',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // `completed_at` se rellena al entrar en Done y se limpia al salir.
        static::saving(function (Task $task) {
            if ($task->status === TaskStatus::Done) {
                $task->completed_at ??= now();
            } else {
                $task->completed_at = null;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
