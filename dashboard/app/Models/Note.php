<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Nota. El cuerpo (`body`) se guarda como Markdown plano.
 * Borrado suave: al eliminarla pasa a la papelera.
 */
class Note extends Model
{
    use SoftDeletes;

    protected $fillable = ['folder_id', 'title', 'icon', 'body', 'pinned', 'position'];

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
