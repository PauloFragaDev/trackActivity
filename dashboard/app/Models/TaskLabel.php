<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Etiqueta del catálogo del tablero Kanban. Se asocia a N tareas vía pivote
 * task_label_task. La paleta se gestiona en /task-labels.
 */
class TaskLabel extends Model
{
    protected $fillable = ['title', 'color', 'position'];

    protected $casts = [
        'position' => 'integer',
    ];

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_label_task', 'label_id', 'task_id');
    }
}
