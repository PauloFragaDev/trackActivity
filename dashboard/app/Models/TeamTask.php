<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TeamTask extends TeamModel
{
    use SoftDeletes;

    protected $table    = 'tasks';
    protected $fillable = [
        'project_id', 'assignee_id', 'created_by_id', 'title', 'description',
        'status', 'priority', 'due_date', 'position', 'completed_at',
    ];
    protected $attributes = ['status' => 'todo', 'position' => 0];
    protected $casts = [
        'project_id'     => 'integer',
        'assignee_id'    => 'integer',
        'created_by_id'  => 'integer',
        'status'         => TaskStatus::class,
        'priority'       => TaskPriority::class,
        'due_date'       => 'date',
        'position'       => 'integer',
        'completed_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (TeamTask $task) {
            if ($task->status === TaskStatus::Done) {
                $task->completed_at ??= now();
            } else {
                $task->completed_at = null;
            }
        });
    }

    /** Team tasks have no time-tracking entries; always returns 0. */
    public function loggedMinutes(): int
    {
        return 0;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(TeamProject::class, 'project_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'assignee_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'created_by_id');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(TeamTaskLabel::class, 'task_label_task', 'task_id', 'label_id')
            ->orderBy('task_labels.position')
            ->orderBy('task_labels.title');
    }

    public function checkboxes(): HasMany
    {
        return $this->hasMany(TeamTaskCheckbox::class, 'task_id')
            ->orderBy('position')->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TeamTaskComment::class, 'task_id')->orderBy('created_at');
    }
}
