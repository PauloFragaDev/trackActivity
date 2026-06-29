<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamTaskComment extends TeamModel
{
    protected $table    = 'task_comments';
    protected $fillable = ['task_id', 'body', 'author_name', 'author_token'];
    protected $casts    = ['task_id' => 'integer'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TeamTask::class, 'task_id');
    }
}
