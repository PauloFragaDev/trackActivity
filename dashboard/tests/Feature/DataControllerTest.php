<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_data_page_loads(): void
    {
        $this->get('/data')->assertOk()->assertSee('Copias de seguridad');
    }

    public function test_export_notes_returns_a_zip(): void
    {
        \App\Models\Note::create(['title' => 'Mi nota', 'body' => 'contenido']);

        $this->get('/data/export/notes')->assertOk()->assertDownload();
    }

    public function test_export_data_returns_json(): void
    {
        \App\Models\Note::create(['title' => 'Exportable']);

        $this->get('/data/export/data')->assertOk()
            ->assertJsonStructure(['exported_at', 'note_folders', 'notes', 'projects', 'manual_entries']);
    }
}
