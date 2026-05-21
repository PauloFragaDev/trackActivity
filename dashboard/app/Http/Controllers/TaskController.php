<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Tablero Kanban de tareas. Las columnas son los valores de TaskStatus.
 */
class TaskController extends Controller
{
    public function index(Request $request): View
    {
        $projectId = $request->integer('project') ?: null;
        $priority  = $request->input('priority') ?: null;

        $tasks = Task::with(['project', 'manualEntries'])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($priority, fn ($q) => $q->where('priority', $priority))
            ->orderBy('position')
            ->get()
            ->groupBy(fn (Task $t) => $t->status->value);

        return view('tasks.board', [
            'columns'    => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'tasks'      => $tasks,
            'projects'   => Project::orderBy('code')->get(),
            'projectId'  => $projectId,
            'priority'   => $priority,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTask($request);
        $data['position'] = (Task::where('status', $data['status'])->max('position') ?? -1) + 1;

        Task::create($data);

        return redirect()->route('tasks.index')->with('status', 'Tarea creada.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $task->update($this->validateTask($request));

        return redirect()->route('tasks.index')->with('status', 'Tarea actualizada.');
    }

    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return redirect()->route('tasks.index')->with('status', 'Tarea eliminada.');
    }

    /** Mueve una tarea de columna / posición (endpoint AJAX del drag & drop). */
    public function move(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'status'   => ['required', Rule::enum(TaskStatus::class)],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $oldStatus = $task->status->value;

        $task->status = TaskStatus::from($data['status']);
        $task->save();   // el hook saving sincroniza completed_at

        $this->reindex($data['status'], $task->id, (int) $data['position']);
        if ($oldStatus !== $data['status']) {
            $this->reindex($oldStatus);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Reescribe las posiciones 0..n de una columna. Si se pasa $insertId,
     * esa tarea se coloca en la posición $insertAt.
     */
    private function reindex(string $status, ?int $insertId = null, int $insertAt = 0): void
    {
        $ids = Task::query()
            ->where('status', $status)
            ->when($insertId !== null, fn ($q) => $q->where('id', '!=', $insertId))
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($insertId !== null) {
            array_splice($ids, max(0, min($insertAt, count($ids))), 0, [$insertId]);
        }

        foreach ($ids as $i => $id) {
            Task::where('id', $id)->update(['position' => $i]);
        }
    }

    /** @return array<string,mixed> */
    private function validateTask(Request $request): array
    {
        return $request->validate([
            'title'       => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'project_id'  => ['nullable', 'integer', 'exists:projects,id'],
            'status'      => ['required', Rule::enum(TaskStatus::class)],
            'priority'    => ['nullable', Rule::enum(TaskPriority::class)],
            'due_date'    => ['nullable', 'date'],
        ]);
    }
}
