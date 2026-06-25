<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamProject extends TeamModel
{
    protected $table    = 'projects';
    protected $fillable = ['code', 'name', 'color', 'description'];

    public function tasks(): HasMany
    {
        return $this->hasMany(TeamTask::class, 'project_id');
    }
}
