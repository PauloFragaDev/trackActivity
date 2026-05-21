<?php

namespace Tests\Feature;

use App\Models\Note;
use App\Models\NoteFolder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD de carpetas de notas (módulo Notas — N1).
 */
class NoteFolderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_creates_a_folder(): void
    {
        $this->post('/note-folders', ['name' => 'Ideas'])->assertRedirect();

        $this->assertDatabaseHas('note_folders', ['name' => 'Ideas', 'parent_id' => null]);
    }

    public function test_store_creates_a_nested_folder(): void
    {
        $parent = NoteFolder::create(['name' => 'Padre']);

        $this->post('/note-folders', ['name' => 'Hija', 'parent_id' => $parent->id])
            ->assertRedirect();

        $this->assertDatabaseHas('note_folders', ['name' => 'Hija', 'parent_id' => $parent->id]);
    }

    public function test_update_renames_a_folder(): void
    {
        $folder = NoteFolder::create(['name' => 'Viejo nombre']);

        $this->patch("/note-folders/{$folder->id}", ['name' => 'Nombre nuevo'])->assertRedirect();

        $this->assertSame('Nombre nuevo', $folder->fresh()->name);
    }

    public function test_destroy_moves_its_notes_to_the_root(): void
    {
        $folder = NoteFolder::create(['name' => 'Temporal']);
        $note   = Note::create(['title' => 'Nota dentro', 'folder_id' => $folder->id]);

        $this->delete("/note-folders/{$folder->id}")->assertRedirect();

        $this->assertDatabaseMissing('note_folders', ['id' => $folder->id]);
        // nullOnDelete: la nota sobrevive, queda en la raíz.
        $this->assertNull($note->fresh()->folder_id);
    }

    public function test_destroy_moves_its_subfolders_to_the_root(): void
    {
        $parent = NoteFolder::create(['name' => 'Padre']);
        $child  = NoteFolder::create(['name' => 'Hija', 'parent_id' => $parent->id]);

        $this->delete("/note-folders/{$parent->id}")->assertRedirect();

        $this->assertNull($child->fresh()->parent_id);
    }

    public function test_store_rejects_missing_name(): void
    {
        $this->post('/note-folders', [])->assertSessionHasErrors('name');
    }
}
