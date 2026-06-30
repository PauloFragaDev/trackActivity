<?php

namespace App\Http\Controllers;

use App\Models\Note;
use App\Models\NoteFolder;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
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

        $isTrash       = $request->boolean('trash');
        $search        = trim((string) $request->query('q', ''));
        $folderId      = $request->integer('folder') ?: null;
        $currentFolder = $folderId ? $folders->firstWhere('id', $folderId) : null;
        $trashCount    = Note::onlyTrashed()->count();

        if ($isTrash) {
            // Papelera: notas eliminadas (soft-deleted).
            $notes       = Note::onlyTrashed()->orderByDesc('deleted_at')->get();
            $currentNote = null;
        } else {
            $query = Note::query()->orderByDesc('pinned');

            if ($search !== '') {
                // Búsqueda en título y cuerpo, sobre todas las carpetas.
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('body', 'like', "%{$search}%");
                })->orderByDesc('updated_at');
            } else {
                $query
                    ->when(
                        $folderId,
                        fn ($q) => $q->where('folder_id', $folderId),
                        fn ($q) => $q->whereNull('folder_id'),
                    )
                    ->orderBy('position')
                    ->orderByDesc('updated_at');
            }

            $notes       = $query->get();
            $noteId      = $request->integer('note') ?: null;
            $currentNote = $noteId ? Note::find($noteId) : $notes->first();
        }

        // Subcarpetas de la carpeta actual (o de raíz si no hay carpeta seleccionada)
        $subfolders = $currentFolder
            ? $currentFolder->children()->orderBy('position')->orderBy('name')->get()
            : collect();

        $parentFolder = $currentFolder?->parent;
        $navBack = $request->boolean('back');

        // Full path para el select de carpeta en el editor
        $folderOptions = $folders->map(function ($f) use ($folders) {
            $path  = $f->name;
            $pid   = $f->parent_id;
            $guard = 0;
            while ($pid && $guard++ < 8 && $p = $folders->firstWhere('id', $pid)) {
                $path = $p->name . ' / ' . $path;
                $pid  = $p->parent_id;
            }
            return ['id' => $f->id, 'name' => $path, 'folder' => $f];
        })->sortBy('name');

        // Ruta de carpetas (breadcrumb) de la nota abierta, de raíz a hoja.
        $breadcrumb = [];
        if ($currentNote && $currentNote->folder_id) {
            $fid   = $currentNote->folder_id;
            $guard = 0;
            while ($fid && $guard++ < 20 && $folder = $folders->firstWhere('id', $fid)) {
                array_unshift($breadcrumb, $folder);
                $fid = $folder->parent_id;
            }
        }

        // Wikilinks: dos lados de la relación, ambos materializados en
        // note_links por el observer de Note.
        //   · backlinks: notas que enlazan a la abierta.
        //   · outgoing: títulos que la abierta menciona (resueltos y huérfanos).
        $backlinks = collect();
        $outgoing  = collect();
        if ($currentNote) {
            $backlinks = Note::query()
                ->whereIn('id', function ($sub) use ($currentNote) {
                    $sub->select('source_note_id')
                        ->from('note_links')
                        ->where('target_note_id', $currentNote->id);
                })
                ->get(['id', 'title', 'icon']);

            $outgoing = $currentNote->outgoingLinks()
                ->with('target:id,title,icon')
                ->get();
        }

        return view('notes.index', [
            'folders'       => $folders,
            'currentFolder' => $currentFolder,
            'folderId'      => $folderId,
            'notes'         => $notes,
            'currentNote'   => $currentNote,
            'search'        => $search,
            'isTrash'       => $isTrash,
            'trashCount'    => $trashCount,
            'breadcrumb'    => $breadcrumb,
            'backlinks'     => $backlinks,
            'outgoing'      => $outgoing,
            'projects'      => Project::orderBy('code')->get(),
            'subfolders'    => $subfolders,
            'parentFolder'  => $parentFolder,
            'navBack'       => $navBack,
            'folderOptions' => $folderOptions,
        ]);
    }

    /** Lista ligera de notas (JSON) para el quick switcher (Ctrl+K). */
    public function quick(): JsonResponse
    {
        $notes = Note::with('folder:id,name')
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'icon', 'folder_id'])
            ->map(fn (Note $n) => [
                'id'     => $n->id,
                'title'  => $n->title,
                'icon'   => $n->icon,
                'folder' => $n->folder?->name,
            ]);

        return response()->json($notes);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title'      => ['required', 'string', 'max:200'],
            'icon'       => ['nullable', 'string', 'max:16'],
            'folder_id'  => ['nullable', 'integer', 'exists:note_folders,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'body'       => ['nullable', 'string'],
        ]);

        $note = Note::create([
            'title'      => $data['title'],
            'icon'       => $data['icon'] ?? null,
            'folder_id'  => $data['folder_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'body'       => $data['body'] ?? null,
        ]);

        return redirect()
            ->route('notes.index', ['folder' => $note->folder_id, 'note' => $note->id])
            ->with('status', 'Nota creada.');
    }

    public function update(Request $request, Note $note): RedirectResponse|Response
    {
        $data = $request->validate([
            'title'      => ['required', 'string', 'max:200'],
            'icon'       => ['nullable', 'string', 'max:16'],
            'body'       => ['nullable', 'string'],
            'folder_id'  => ['nullable', 'integer', 'exists:note_folders,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        $note->update([
            'title'      => $data['title'],
            'icon'       => $data['icon'] ?? null,
            'body'       => $data['body'] ?? null,
            'folder_id'  => $data['folder_id'] ?? null,
            'project_id' => $data['project_id'] ?? null,
            'pinned'     => $request->boolean('pinned'),
        ]);

        // Autosave del editor (AJAX): responde 204, sin redirección.
        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()
            ->route('notes.index', ['folder' => $note->folder_id, 'note' => $note->id])
            ->with('status', 'Nota guardada.');
    }

    public function destroy(Note $note): RedirectResponse
    {
        $folderId = $note->folder_id;
        $note->delete();   // soft delete: pasa a la papelera

        return redirect()
            ->route('notes.index', ['folder' => $folderId])
            ->with('status', 'Nota movida a la papelera.');
    }

    /** Restaura una nota desde la papelera. */
    public function restore(int $id): RedirectResponse
    {
        $note = Note::onlyTrashed()->findOrFail($id);
        $note->restore();

        return redirect()
            ->route('notes.index', ['folder' => $note->folder_id, 'note' => $note->id])
            ->with('status', 'Nota restaurada.');
    }

    /** Vacía la papelera: borra definitivamente las notas eliminadas. */
    public function emptyTrash(): RedirectResponse
    {
        Note::onlyTrashed()->forceDelete();

        return redirect()
            ->route('notes.index')
            ->with('status', 'Papelera vaciada.');
    }

    /** Fija/desfija una nota (acción rápida desde la lista). */
    public function togglePin(Note $note): RedirectResponse
    {
        $note->update(['pinned' => ! $note->pinned]);

        return redirect()
            ->route('notes.index', ['folder' => $note->folder_id, 'note' => $note->id])
            ->with('status', $note->pinned ? 'Nota fijada.' : 'Nota desfijada.');
    }

    /** Mueve una nota a otra carpeta (o a la raíz). */
    public function move(Request $request, Note $note): JsonResponse
    {
        $data = $request->validate([
            'folder_id' => ['nullable', 'integer', 'exists:note_folders,id'],
        ]);

        $note->update(['folder_id' => $data['folder_id'] ?? null]);

        return response()->json(['ok' => true]);
    }

    /** Sube una imagen adjunta a una nota y devuelve su URL pública. */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpeg,png,gif,webp', 'max:5120'],
        ]);

        $path = $request->file('image')->store('note-images', 'public');

        return response()->json(['url' => Storage::url($path)]);
    }
}
