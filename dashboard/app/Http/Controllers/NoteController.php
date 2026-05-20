<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notas: vista de carpetas + lista + editor, y CRUD de notas.
 *
 * El cuerpo se guarda como Markdown plano. En N1 el editor es un textarea;
 * el editor WYSIWYG llega en N3 (ver docs/16-notes-plan.md).
 */
class NoteController extends Controller
{
    public function index(Request $request): View
    {
        $folders = NoteFolder::query()->orderBy('position')->orderBy('name')->get();

        $folderId      = $request->integer('folder') ?: null;
        $currentFolder = $folderId ? $folders->firstWhere('id', $folderId) : null;

        $notes = Note::query()
            ->when(
                $folderId,
                fn ($q) => $q->where('folder_id', $folderId),
                fn ($q) => $q->whereNull('folder_id'),
            )
            ->orderByDesc('pinned')
            ->orderBy('position')
            ->orderByDesc('updated_at')
            ->get();

        $noteId      = $request->integer('note') ?: null;
        $currentNote = $noteId ? Note::find($noteId) : $notes->first();

        return view('notes.index', [
            'folders'       => $folders,
            'currentFolder' => $currentFolder,
            'folderId'      => $folderId,
            'notes'         => $notes,
            'currentNote'   => $currentNote,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'     => ['required', 'string', 'max:200'],
            'folder_id' => ['nullable', 'integer', 'exists:note_folders,id'],
            'body'      => ['nullable', 'string'],
        ]);

        $note = Note::create([
            'title'     => $data['title'],
            'folder_id' => $data['folder_id'] ?? null,
            'body'      => $data['body'] ?? null,
        ]);

        return redirect()
            ->route('notes.index', ['folder' => $note->folder_id, 'note' => $note->id])
            ->with('status', 'Nota creada.');
    }

    public function update(Request $request, Note $note): RedirectResponse
    {
        $data = $request->validate([
            'title'     => ['required', 'string', 'max:200'],
            'body'      => ['nullable', 'string'],
            'folder_id' => ['nullable', 'integer', 'exists:note_folders,id'],
        ]);

        $note->update([
            'title'     => $data['title'],
            'body'      => $data['body'] ?? null,
            'folder_id' => $data['folder_id'] ?? null,
            'pinned'    => $request->boolean('pinned'),
        ]);

        return redirect()
            ->route('notes.index', ['folder' => $note->folder_id, 'note' => $note->id])
            ->with('status', 'Nota guardada.');
    }

    public function destroy(Note $note): RedirectResponse
    {
        $folderId = $note->folder_id;
        $note->delete();

        return redirect()
            ->route('notes.index', ['folder' => $folderId])
            ->with('status', 'Nota eliminada.');
    }
}
