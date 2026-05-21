<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = ['code', 'name', 'color', 'description'];

    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(ProjectMapping::class);
    }

    public function timeBlocks(): HasMany
    {
        return $this->hasMany(TimeBlock::class, 'dominant_project_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
