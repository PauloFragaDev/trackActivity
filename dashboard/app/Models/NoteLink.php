<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wikilink materializado de una nota a otra (o a un título que aún no
 * tiene nota — target_note_id queda null y se resuelve cuando alguien
 * cree la nota destino).
 *
 * La fila siempre guarda el `target_title` tal cual lo tecleó el usuario
 * en `[[…]]`, para poder seguir mostrando el link incluso si el target
 * no existe.
 */
class NoteLink extends Model
{
    public $timestamps = false;
    protected $fillable = ['source_note_id', 'target_note_id', 'target_title', 'created_at'];

    protected $casts = [
        'source_note_id' => 'integer',
        'target_note_id' => 'integer',
        'created_at'     => 'datetime',
    ];

    public function source(): BelongsTo { return $this->belongsTo(Note::class, 'source_note_id'); }
    public function target(): BelongsTo { return $this->belongsTo(Note::class, 'target_note_id'); }
}
