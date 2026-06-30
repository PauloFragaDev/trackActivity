<?php

namespace App\Http\Controllers;

use App\Models\NoteFolder;
use Illuminate\Http\JsonResponse;
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
            'icon'      => ['nullable', 'string', 'max:16'],
            'parent_id' => ['nullable', 'integer', 'exists:note_folders,id'],
        ]);

        $folder = NoteFolder::create([
            'name'      => $data['name'],
            'icon'      => $data['icon'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
        ]);

        return redirect()
            ->route('notes.index', ['folder' => $folder->id])
            ->with('status', 'Carpeta creada.');
    }

    public function update(Request $request, NoteFolder $noteFolder): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:16'],
        ]);

        $noteFolder->update([
            'name' => $data['name'],
            'icon' => $data['icon'] ?? null,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'name' => $noteFolder->name]);
        }

        return redirect()
            ->route('notes.index', ['folder' => $noteFolder->id])
            ->with('status', 'Carpeta renombrada.');
    }

    /** Mueve una carpeta a otro padre (o a la raíz). Detecta ciclos antes de guardar. */
    public function move(Request $request, NoteFolder $noteFolder): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:note_folders,id'],
        ]);

        $targetId = $data['parent_id'] ?? null;

        if ($targetId !== null) {
            if ($targetId === $noteFolder->id) {
                return response()->json(['error' => 'No puedes mover una carpeta a sí misma.'], 422);
            }
            $all  = NoteFolder::all()->keyBy('id');
            $walk = $all[$targetId]?->parent_id ?? null;
            for ($i = 0; $i < 20 && $walk; $i++) {
                if ($walk === $noteFolder->id) {
                    return response()->json(['error' => 'No se puede mover: se crearía un ciclo.'], 422);
                }
                $walk = $all[$walk]?->parent_id ?? null;
            }
        }

        $noteFolder->update(['parent_id' => $targetId]);

        return response()->json(['ok' => true]);
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
