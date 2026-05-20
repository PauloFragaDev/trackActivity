<?php

namespace App\Http\Controllers;

use App\Models\NoteFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Carpetas de notas. El re-anidado de una carpeta existente (mover de
 * padre) se hace solo al crearla en N1 — evita ciclos sin necesidad de
 * comprobaciones; mover carpetas llega más adelante.
 */
class NoteFolderController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'parent_id' => ['nullable', 'integer', 'exists:note_folders,id'],
        ]);

        $folder = NoteFolder::create([
            'name'      => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
        ]);

        return redirect()
            ->route('notes.index', ['folder' => $folder->id])
            ->with('status', 'Carpeta creada.');
    }

    public function update(Request $request, NoteFolder $noteFolder): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $noteFolder->update(['name' => $data['name']]);

        return redirect()
            ->route('notes.index', ['folder' => $noteFolder->id])
            ->with('status', 'Carpeta renombrada.');
    }

    public function destroy(NoteFolder $noteFolder): RedirectResponse
    {
        // nullOnDelete: las notas y subcarpetas de dentro pasan a la raíz.
        $noteFolder->delete();

        return redirect()
            ->route('notes.index')
            ->with('status', 'Carpeta eliminada.');
    }
}
