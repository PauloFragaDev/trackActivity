<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class TeamTaskLabel extends TeamModel
{
    protected $table    = 'task_labels';
    protected $fillable = ['title', 'color', 'position'];

    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(TeamTask::class, 'task_label_task', 'label_id', 'task_id');
    }
}
