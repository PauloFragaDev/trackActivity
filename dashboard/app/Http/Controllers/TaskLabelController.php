<?php

namespace App\Http\Controllers;

use App\Models\TaskLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Paleta global de etiquetas del tablero Kanban. Las etiquetas se gestionan
 * en /task-labels y luego se aplican a las tareas en su modal de edición.
 */
class TaskLabelController extends Controller
{
    /** Colores presentados al crear/editar una etiqueta (inspirados en code-kanban). */
    public const COLORS = [
        ['name' => 'Gris',    'hex' => '#9CA3AF'],
        ['name' => 'Azul',    'hex' => '#3B82F6'],
        ['name' => 'Cian',    'hex' => '#06B6D4'],
        ['name' => 'Lima',    'hex' => '#84CC16'],
        ['name' => 'Ámbar',   'hex' => '#F59E0B'],
        ['name' => 'Rojo',    'hex' => '#DC2626'],
        ['name' => 'Púrpura', 'hex' => '#8B5CF6'],
        ['name' => 'Rosa',    'hex' => '#EC4899'],
    ];

    public function index(): View
    {
        return view('task-labels.index', [
            'labels' => TaskLabel::orderBy('position')->orderBy('title')->get(),
            'colors' => self::COLORS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateLabel($request);
        $data['position'] = (TaskLabel::max('position') ?? -1) + 1;
        TaskLabel::create($data);

        return redirect()->route('task-labels.index')->with('status', 'Etiqueta creada.');
    }

    public function update(Request $request, TaskLabel $taskLabel): RedirectResponse
    {
        $taskLabel->update($this->validateLabel($request));

        return redirect()->route('task-labels.index')->with('status', 'Etiqueta actualizada.');
    }

    public function destroy(TaskLabel $taskLabel): RedirectResponse
    {
        $taskLabel->delete();

        return redirect()->route('task-labels.index')->with('status', 'Etiqueta eliminada.');
    }

    /** @return array<string,mixed> */
    private function validateLabel(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:60'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
    }
}
