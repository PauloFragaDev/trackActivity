<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
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

        $tasks = Task::with('project')
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->orderBy('position')
            ->get()
            ->groupBy(fn (Task $t) => $t->status->value);

        return view('tasks.board', [
            'columns'    => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'tasks'      => $tasks,
            'projects'   => Project::orderBy('code')->get(),
            'projectId'  => $projectId,
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
