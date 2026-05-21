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

    public function test_destroy_deletes_a_note(): void
    {
        $note = Note::create(['title' => 'N']);

        $this->delete("/notes/{$note->id}")->assertRedirect();

        $this->assertDatabaseMissing('notes', ['id' => $note->id]);
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
}
