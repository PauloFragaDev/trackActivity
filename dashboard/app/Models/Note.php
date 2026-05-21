<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nota. El cuerpo (`body`) se guarda como Markdown plano.
 */
class Note extends Model
{
    protected $fillable = ['folder_id', 'title', 'body', 'pinned', 'position'];

    /** Defaults en memoria (coinciden con los de la migración). */
    protected $attributes = [
        'pinned'   => false,
        'position' => 0,
    ];

    protected $casts = [
        'folder_id' => 'integer',
        'pinned'    => 'boolean',
        'position'  => 'integer',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(NoteFolder::class, 'folder_id');
    }
}
