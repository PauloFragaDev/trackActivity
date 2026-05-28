<?php

namespace App\Services;

use App\Models\Note;
use App\Models\NoteLink;
use Illuminate\Support\Carbon;

/**
 * Pieza única responsable de mantener `note_links` en sincronía con el
 * cuerpo de las notas. Convención `[[Título]]` con dos detalles:
 *
 *   1. Los corchetes ANIDADOS no se permiten: la regex es no-greedy y
 *      detiene el match en el primer `]]`. `[[foo [[bar]]` da `bar`.
 *   2. Comparación de títulos insensible a may/min ASCII (Spanish-ish:
 *      "Café" y "café" matchean por strtolower). No quitamos tildes
 *      porque el dueño es single-user y "noté" ≠ "note" deliberadamente.
 *
 * Cuándo se llama:
 *   · saving de una Note → rematerializeFromSource() recalcula sus
 *     enlaces salientes.
 *   · creating de una Note → adoptOrphans() vincula huérfanos cuyo
 *     target_title coincida con el título nuevo.
 */
class WikilinkResolver
{
    /** Extrae los títulos de los wikilinks de un body, en orden y dedup. */
    public static function extractTitles(?string $body): array
    {
        if ($body === null || $body === '') return [];
        preg_match_all('/\[\[([^\[\]\n]{1,200})\]\]/u', $body, $m);
        $titles = array_map('trim', $m[1] ?? []);
        $titles = array_filter($titles, fn ($t) => $t !== '');
        return array_values(array_unique($titles, SORT_STRING));
    }

    /** Rehace la tabla `note_links` para esta nota a partir de su body. */
    public static function rematerializeFromSource(Note $note): void
    {
        $titles = self::extractTitles($note->body);

        // Borra los antiguos: barato y evita drift cuando se renombra/borra
        // un wikilink.
        NoteLink::query()->where('source_note_id', $note->id)->delete();
        if (empty($titles)) return;

        // Resuelve title → id en una sola consulta (lowercased). Notas que
        // todavía no existen se quedan con target_note_id = null (huérfano).
        $lowered = array_map(fn ($t) => mb_strtolower($t), $titles);
        $matched = Note::query()
            ->whereIn(\DB::raw('lower(title)'), $lowered)
            ->where('id', '!=', $note->id) // no auto-link
            ->pluck('id', 'title');
        $matchedByLower = [];
        foreach ($matched as $title => $id) {
            $matchedByLower[mb_strtolower((string) $title)] = $id;
        }

        $rows = [];
        foreach ($titles as $title) {
            $rows[] = [
                'source_note_id' => $note->id,
                'target_note_id' => $matchedByLower[mb_strtolower($title)] ?? null,
                'target_title'   => $title,
                'created_at'     => Carbon::now(),
            ];
        }
        NoteLink::query()->insert($rows);
    }

    /**
     * Cuando se crea (o renombra) una nota, los enlaces huérfanos cuyo
     * `target_title` matchea el nuevo título pasan a apuntarle.
     */
    public static function adoptOrphans(Note $note): void
    {
        if ($note->title === null || $note->title === '') return;
        NoteLink::query()
            ->whereNull('target_note_id')
            ->whereRaw('lower(target_title) = ?', [mb_strtolower($note->title)])
            ->where('source_note_id', '!=', $note->id)
            ->update(['target_note_id' => $note->id]);
    }

    /**
     * Convierte el body en HTML mínimo donde los `[[Título]]` son links
     * navegables, escapando el resto. NO renderiza Markdown — solo sirve
     * para previews de lista, backlinks y similares.
     */
    public static function renderInline(?string $body, int $maxChars = 200): string
    {
        if ($body === null || $body === '') return '';
        $snippet = mb_substr($body, 0, $maxChars);
        // Buscamos primero los wikilinks, los reemplazamos por marcadores
        // únicos, escapamos HTML, y luego restauramos los <a>.
        $tokens = [];
        $i = 0;
        $tokenized = preg_replace_callback('/\[\[([^\[\]\n]{1,200})\]\]/u',
            function ($m) use (&$tokens, &$i) {
                $marker = "\x00WL{$i}\x00";
                $tokens[$marker] = trim($m[1]);
                $i++;
                return $marker;
            }, $snippet) ?? $snippet;
        $escaped = e($tokenized);
        foreach ($tokens as $marker => $title) {
            $target = Note::query()->whereRaw('lower(title) = ?', [mb_strtolower($title)])->first(['id']);
            $href   = $target ? '/notes?note=' . $target->id : '/notes?q=' . urlencode($title);
            $class  = $target ? 'wikilink' : 'wikilink wikilink--missing';
            $repl   = sprintf('<a href="%s" class="%s">%s</a>', e($href), $class, e($title));
            $escaped = str_replace($marker, $repl, $escaped);
        }
        return $escaped;
    }
}
