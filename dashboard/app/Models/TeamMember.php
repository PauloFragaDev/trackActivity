<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamMember extends TeamModel
{
    protected $fillable = ['name', 'color', 'position'];

    public function tasks(): HasMany
    {
        return $this->hasMany(TeamTask::class, 'assignee_id');
    }

    /** Iniciales para el avatar (hasta 2 letras). */
    public function initials(): string
    {
        $parts = explode(' ', trim($this->name));
        return strtoupper(
            count($parts) >= 2
                ? $parts[0][0] . $parts[1][0]
                : ($parts[0][0] ?? '?')
        );
    }
}
