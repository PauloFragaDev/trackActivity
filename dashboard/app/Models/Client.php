<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'company', 'email', 'phone', 'website', 'notes', 'color',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** IDs de los proyectos del cliente (para roll-up de tareas/notas/tiempo). */
    public function projectIds(): array
    {
        return $this->projects()->pluck('id')->all();
    }
}
