<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskCheckbox;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Endpoints AJAX para las subtareas (checkboxes) de una tarea Kanban.
 * Se gestionan desde el modal de edición, una operación por interacción
 * (añadir / marcar / desmarcar / borrar) — sin recargar el board.
 */
class TaskCheckboxController extends Controller
{
    public function store(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
        ]);

        $checkbox = $task->checkboxes()->create([
            'title'    => $data['title'],
            'position' => ($task->checkboxes()->max('position') ?? -1) + 1,
        ]);

        return response()->json($checkbox);
    }

    public function update(Request $request, Task $task, TaskCheckbox $taskCheckbox): JsonResponse
    {
        $this->ensureBelongs($task, $taskCheckbox);

        $data = $request->validate([
            'title'   => ['sometimes', 'required', 'string', 'max:200'],
            'checked' => ['sometimes', 'boolean'],
        ]);
        $taskCheckbox->update($data);

        return response()->json($taskCheckbox);
    }

    public function destroy(Task $task, TaskCheckbox $taskCheckbox): Response
    {
        $this->ensureBelongs($task, $taskCheckbox);
        $taskCheckbox->delete();

        return response()->noContent();
    }

    private function ensureBelongs(Task $task, TaskCheckbox $checkbox): void
    {
        abort_unless($checkbox->task_id === $task->id, 404);
    }
}
