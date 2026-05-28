<?php

namespace App\Models;

use App\Services\WikilinkResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Nota. El cuerpo (`body`) se guarda como Markdown plano.
 * Borrado suave: al eliminarla pasa a la papelera.
 */
class Note extends Model
{
    use SoftDeletes;

    protected $fillable = ['folder_id', 'project_id', 'title', 'icon', 'body', 'pinned', 'position'];

    /** Defaults en memoria (coinciden con los de la migración). */
    protected $attributes = [
        'pinned'   => false,
        'position' => 0,
    ];

    protected $casts = [
        'folder_id'  => 'integer',
        'project_id' => 'integer',
        'pinned'     => 'boolean',
        'position'   => 'integer',
    ];

    public function folder(): BelongsTo
    {
        return $this->belongsTo(NoteFolder::class, 'folder_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Enlaces salientes (`[[…]]` que aparecen en mi body). */
    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(NoteLink::class, 'source_note_id');
    }

    /** Enlaces entrantes — otras notas cuyo body me referencia (backlinks). */
    public function incomingLinks(): HasMany
    {
        return $this->hasMany(NoteLink::class, 'target_note_id');
    }

    /**
     * Hooks de wikilinks. Después de guardar, re-materializo mis enlaces
     * salientes; cuando la nota se acaba de crear o se renombra, los
     * huérfanos que esperaban por mi título me adoptan. Todo ocurre en el
     * mismo flujo del save — el usuario no nota el coste.
     */
    protected static function booted(): void
    {
        static::saved(function (Note $note) {
            WikilinkResolver::rematerializeFromSource($note);
            if ($note->wasRecentlyCreated || $note->wasChanged('title')) {
                WikilinkResolver::adoptOrphans($note);
            }
        });
    }

    /**
     * Extracto en texto plano del cuerpo, para la lista de notas:
     * quita HTML y la sintaxis Markdown más común (encabezados, viñetas,
     * énfasis, enlaces, código) y colapsa los espacios.
     */
    public function preview(int $length = 64): string
    {
        $text = (string) $this->body;
        $text = strip_tags($text);                                      // <br>, <b>…
        $text = preg_replace('/```.*?```/s', ' ', $text);               // bloques de código
        $text = preg_replace('/^\s*([#>]+|[-*+]|\d+\.)\s+/m', '', $text); // marcadores de línea
        $text = preg_replace('/!?\[([^\]]*)\]\([^)]*\)/', '$1', $text);  // enlaces e imágenes
        $text = preg_replace('/(\*\*|\*|__|_|~~|`)/', '', $text);        // énfasis y código en línea
        $text = preg_replace('/\s+/', ' ', (string) $text);              // colapsar espacios/saltos

        return Str::limit(trim((string) $text), $length);
    }
}
