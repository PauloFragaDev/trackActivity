<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Item de la subtarea (checkbox) de una tarea Kanban.
 */
class TaskCheckbox extends Model
{
    protected $fillable = ['task_id', 'title', 'checked', 'position'];

    protected $attributes = [
        'checked'  => false,
        'position' => 0,
    ];

    protected $casts = [
        'task_id'  => 'integer',
        'checked'  => 'boolean',
        'position' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
