<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tarea del tablero Kanban. `completed_at` se sincroniza con el estado Done.
 */
class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id', 'title', 'description', 'status', 'priority',
        'due_date', 'position', 'completed_at',
        'github_item_id', 'github_synced_at', 'github_dirty',
        'kanban_card_id', 'kanban_synced_at',
    ];

    /** Defaults en memoria (coinciden con los de la migración). */
    protected $attributes = [
        'status'       => 'todo',
        'position'     => 0,
        'github_dirty' => false,
    ];

    protected $casts = [
        'project_id'   => 'integer',
        'status'       => TaskStatus::class,
        'priority'     => TaskPriority::class,
        'due_date'     => 'date',
        'position'         => 'integer',
        'completed_at'     => 'datetime',
        'github_synced_at' => 'datetime',
        'github_dirty'     => 'boolean',
        'kanban_synced_at' => 'datetime',
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

    public function manualEntries(): HasMany
    {
        return $this->hasMany(ManualEntry::class);
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TaskLabel::class, 'task_label_task', 'task_id', 'label_id')
            ->orderBy('task_labels.position')
            ->orderBy('task_labels.title');
    }

    public function checkboxes(): HasMany
    {
        return $this->hasMany(TaskCheckbox::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at');
    }

    /** Minutos totales registrados contra la tarea vía entradas manuales. */
    public function loggedMinutes(): int
    {
        return (int) $this->manualEntries->sum(fn (ManualEntry $e) => $e->durationMinutes());
    }
}
