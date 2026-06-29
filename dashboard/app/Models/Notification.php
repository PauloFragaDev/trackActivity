<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends TeamModel
{
    public $timestamps = false;

    protected $fillable = ['recipient_id', 'actor_id', 'type', 'task_id', 'payload'];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    protected $attributes = ['payload' => '{}'];

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'recipient_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'actor_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(TeamTask::class, 'task_id');
    }
}
