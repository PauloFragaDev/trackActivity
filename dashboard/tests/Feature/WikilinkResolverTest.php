<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\NoteLink;
use App\Services\WikilinkResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WikilinkResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_extract_titles_dedupes_and_trims(): void
    {
        $titles = WikilinkResolver::extractTitles('foo [[Alpha]] bar [[ Beta  ]] [[Alpha]] baz');
        $this->assertSame(['Alpha', 'Beta'], $titles);
    }

    public function test_saving_a_note_materializes_outgoing_links(): void
    {
        $alpha = Note::create(['title' => 'Alpha']);
        $beta  = Note::create(['title' => 'Beta', 'body' => 'ref a [[Alpha]] y [[NoExiste]].']);

        $links = NoteLink::where('source_note_id', $beta->id)->get()->keyBy('target_title');
        $this->assertSame($alpha->id, $links['Alpha']->target_note_id);
        $this->assertNull($links['NoExiste']->target_note_id);
    }

    public function test_creating_a_note_adopts_orphans(): void
    {
        // Hay un enlace huérfano hacia un título que aún no existe.
        $beta = Note::create(['title' => 'Beta', 'body' => 'apunto a [[Gamma]]']);
        $this->assertNull(
            NoteLink::where('source_note_id', $beta->id)->first()->target_note_id
        );

        $gamma = Note::create(['title' => 'Gamma']);

        $this->assertSame(
            $gamma->id,
            NoteLink::where('source_note_id', $beta->id)->first()->fresh()->target_note_id
        );
    }

    public function test_renaming_a_note_does_not_break_existing_links(): void
    {
        // Caso por documentar: el target_title sigue siendo el viejo hasta
        // que la nota fuente se vuelva a guardar (rematerialización lazy).
        $alpha = Note::create(['title' => 'Alpha']);
        $beta  = Note::create(['title' => 'Beta', 'body' => 'voy a [[Alpha]]']);
        $alpha->update(['title' => 'Renombrada']);

        $link = NoteLink::where('source_note_id', $beta->id)->first();
        $this->assertSame('Alpha', $link->target_title);
        $this->assertSame($alpha->id, $link->target_note_id, 'el id sigue siendo válido');
    }

    public function test_no_self_link(): void
    {
        $note = Note::create(['title' => 'Solo', 'body' => 'auto-ref [[Solo]]']);
        $link = NoteLink::where('source_note_id', $note->id)->first();
        // Deja una fila con title pero sin target_note_id (no me auto-enlazo).
        $this->assertNotNull($link);
        $this->assertNull($link->target_note_id);
    }
}
