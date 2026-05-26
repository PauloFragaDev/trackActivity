<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Comentario dentro de una tarea Kanban.
 */
class TaskComment extends Model
{
    protected $fillable = ['task_id', 'body'];

    protected $casts = [
        'task_id' => 'integer',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
