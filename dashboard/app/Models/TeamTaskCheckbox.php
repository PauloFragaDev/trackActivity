<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamTaskCheckbox extends TeamModel
{
    protected $table    = 'task_checkboxes';
    protected $fillable = ['task_id', 'title', 'checked', 'position'];
    protected $casts    = ['checked' => 'boolean', 'task_id' => 'integer'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TeamTask::class, 'task_id');
    }
}
