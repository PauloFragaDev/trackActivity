<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Carpeta de notas, anidable (parent_id self-referencial).
 */
class NoteFolder extends Model
{
    protected $fillable = ['name', 'icon', 'parent_id', 'position'];

    protected $casts = [
        'parent_id' => 'integer',
        'position'  => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'folder_id');
    }
}
