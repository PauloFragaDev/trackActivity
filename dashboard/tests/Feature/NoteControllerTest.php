<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\NoteFolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD de notas (módulo Notas — N1).
 */
class NoteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_loads(): void
    {
        $this->get('/notes')->assertOk();
    }

    public function test_store_creates_a_note(): void
    {
        $this->post('/notes', ['title' => 'Mi nota', 'body' => '# Hola'])
            ->assertRedirect();

        $note = Note::firstOrFail();
        $this->assertSame('Mi nota', $note->title);
        $this->assertSame('# Hola', $note->body);
        $this->assertNull($note->folder_id);
        $this->assertFalse($note->pinned);
    }

    public function test_store_inside_a_folder(): void
    {
        $folder = NoteFolder::create(['name' => 'Trabajo']);

        $this->post('/notes', ['title' => 'Nota', 'folder_id' => $folder->id])
            ->assertRedirect();

        $this->assertSame($folder->id, Note::firstOrFail()->folder_id);
    }

    public function test_update_modifies_a_note(): void
    {
        $note = Note::create(['title' => 'Original', 'body' => 'x']);

        $this->patch("/notes/{$note->id}", ['title' => 'Editada', 'body' => 'nuevo cuerpo'])
            ->assertRedirect();

        $note->refresh();
        $this->assertSame('Editada', $note->title);
        $this->assertSame('nuevo cuerpo', $note->body);
    }

    public function test_update_can_pin_and_unpin_a_note(): void
    {
        $note = Note::create(['title' => 'N']);

        $this->patch("/notes/{$note->id}", ['title' => 'N', 'pinned' => '1'])->assertRedirect();
        $this->assertTrue($note->fresh()->pinned);

        // Sin el campo pinned → se desfija (la casilla desmarcada no se envía).
        $this->patch("/notes/{$note->id}", ['title' => 'N'])->assertRedirect();
        $this->assertFalse($note->fresh()->pinned);
    }

    public function test_destroy_moves_a_note_to_the_trash(): void
    {
        $note = Note::create(['title' => 'N']);

        $this->delete("/notes/{$note->id}")->assertRedirect();

        // Borrado suave: la nota va a la papelera, no se borra de la BBDD.
        $this->assertSoftDeleted('notes', ['id' => $note->id]);
    }

    public function test_store_rejects_missing_title(): void
    {
        $this->post('/notes', ['body' => 'sin título'])->assertSessionHasErrors('title');
    }

    public function test_store_rejects_nonexistent_folder(): void
    {
        $this->post('/notes', ['title' => 'N', 'folder_id' => 999999])
            ->assertSessionHasErrors('folder_id');
    }

    public function test_search_matches_title_or_body(): void
    {
        Note::create(['title' => 'Pan casero', 'body' => 'mezclar harina y agua']);
        Note::create(['title' => 'Comprar harina', 'body' => 'para el finde']);
        Note::create(['title' => 'Pelicula', 'body' => 'verla el viernes']);

        $res = $this->get('/notes?q=harina')->assertOk();
        $res->assertSee('Pan casero');        // coincide por el cuerpo
        $res->assertSee('Comprar harina');    // coincide por el título
        $res->assertDontSee('Pelicula');
    }

    public function test_toggle_pin(): void
    {
        $note = Note::create(['title' => 'N']);
        $this->assertFalse($note->pinned);

        $this->patch("/notes/{$note->id}/pin")->assertRedirect();
        $this->assertTrue($note->fresh()->pinned);

        $this->patch("/notes/{$note->id}/pin")->assertRedirect();
        $this->assertFalse($note->fresh()->pinned);
    }

    public function test_store_and_update_save_the_icon(): void
    {
        $this->post('/notes', ['title' => 'Con icono', 'icon' => '🎯'])->assertRedirect();
        $note = Note::firstOrFail();
        $this->assertSame('🎯', $note->icon);

        $this->patch("/notes/{$note->id}", ['title' => 'Con icono', 'icon' => '🚀'])->assertRedirect();
        $this->assertSame('🚀', $note->fresh()->icon);
    }

    public function test_restore_brings_a_note_back_from_the_trash(): void
    {
        $note = Note::create(['title' => 'Borrable']);
        $this->delete("/notes/{$note->id}")->assertRedirect();
        $this->assertSoftDeleted('notes', ['id' => $note->id]);

        $this->patch("/notes/{$note->id}/restore")->assertRedirect();
        $this->assertNotSoftDeleted('notes', ['id' => $note->id]);
    }

    public function test_empty_trash_force_deletes_trashed_notes(): void
    {
        $kept    = Note::create(['title' => 'Activa']);
        $trashed = Note::create(['title' => 'En papelera']);
        $trashed->delete();

        $this->delete('/notes/trash')->assertRedirect();

        $this->assertDatabaseMissing('notes', ['id' => $trashed->id]);
        $this->assertDatabaseHas('notes', ['id' => $kept->id]);
    }

    public function test_trash_view_lists_only_deleted_notes(): void
    {
        Note::create(['title' => 'Nota activa']);
        Note::create(['title' => 'Nota en papelera'])->delete();

        $this->get('/notes?trash=1')->assertOk()
            ->assertSee('Nota en papelera')
            ->assertDontSee('Nota activa');
    }

    public function test_preview_strips_markdown(): void
    {
        $note = Note::create(['title' => 'P', 'body' => "## Título\n<br>\n* uno\n* dos"]);

        $preview = $note->preview();
        $this->assertStringNotContainsString('#', $preview);
        $this->assertStringNotContainsString('<br>', $preview);
        $this->assertStringNotContainsString('*', $preview);
        $this->assertStringContainsString('uno', $preview);
    }

    public function test_breadcrumb_is_the_folder_ancestor_path(): void
    {
        $parent = NoteFolder::create(['name' => 'Proyectos']);
        $child  = NoteFolder::create(['name' => 'Web', 'parent_id' => $parent->id]);
        $note   = Note::create(['title' => 'Mi nota', 'folder_id' => $child->id]);

        $breadcrumb = $this->get("/notes?note={$note->id}")->assertOk()->viewData('breadcrumb');

        // De raíz a hoja: [Proyectos, Web].
        $this->assertSame(['Proyectos', 'Web'], collect($breadcrumb)->pluck('name')->all());
    }

    public function test_quick_returns_active_notes_as_json(): void
    {
        Note::create(['title' => 'Alfa']);
        Note::create(['title' => 'Beta'])->delete();   // en la papelera

        $this->getJson('/notes/quick')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Alfa'])
            ->assertJsonMissing(['title' => 'Beta']);
    }
}
