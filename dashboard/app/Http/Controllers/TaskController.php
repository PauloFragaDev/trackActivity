<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskLabel;
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

        $tasks = Task::with(['project', 'manualEntries', 'labels', 'checkboxes', 'comments'])
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
            'labels'     => TaskLabel::orderBy('position')->orderBy('title')->get(),
            'projectId'  => $projectId,
            'priority'   => $priority,
            'mode'       => 'personal',
            'members'    => collect(),
            'assigneeId' => null,
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $this->validateTask($request);
        $labelIds = $data['label_ids'] ?? [];
        unset($data['label_ids']);
        $data['position'] = (Task::where('status', $data['status'])->max('position') ?? -1) + 1;

        $task = Task::create($data);
        $task->labels()->sync($labelIds);

        if ($request->wantsJson()) {
            $task->load(['labels', 'checkboxes', 'comments', 'project']);
            return response()->json([
                'ok'   => true,
                'html' => view('tasks.partials.card', compact('task'))->render(),
            ]);
        }

        return redirect()->route('tasks.index')->with('status', 'Tarea creada.');
    }

    public function update(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $data = $this->validateTask($request);
        $labelIds = $data['label_ids'] ?? [];
        unset($data['label_ids']);

        $task->update($data);
        $task->labels()->sync($labelIds);

        if ($request->wantsJson()) {
            $task->load(['labels', 'checkboxes', 'comments', 'project']);
            return response()->json([
                'ok'   => true,
                'html' => view('tasks.partials.card', compact('task'))->render(),
            ]);
        }

        return redirect()->route('tasks.index')->with('status', 'Tarea actualizada.');
    }

    /**
     * "Archivar" — soft delete. La tarea sale del board pero puede recuperarse
     * desde /tasks/archived. Para borrado definitivo, ver forceDestroy().
     */
    public function destroy(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $task->delete();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('tasks.index')->with('status', 'Tarea archivada.');
    }

    /**
     * Endpoint ligero para que la página /tasks haga polling JS y se
     * entere de cambios desde otras fuentes (la extensión code-kanban,
     * otra pestaña, etc.). Devuelve { latest } con el MAX(updated_at)
     * de las tasks (incluye soft-deleted vía withTrashed).
     *
     * Por qué polling y no SSE en la web: `php artisan serve` tiene
     * workers limitados (PHP_CLI_SERVER_WORKERS=4 en .env). Una conexión
     * SSE bloquea 1 worker permanentemente. Si además la extensión
     * code-kanban abre otro SSE, te quedas con 2 workers para todo el
     * navegador (estáticos, PATCH, etc.). Resultado: la app se atasca.
     * Polling con un endpoint que termina inmediatamente libera el worker
     * tras cada llamada — coexiste sin problema con el SSE de la extensión.
     */
    public function peek(Request $request): \Illuminate\Http\JsonResponse
    {
        $projectId = $request->integer('project') ?: null;
        $q = Task::withTrashed();
        if ($projectId) {
            $q->where('project_id', $projectId);
        }
        $latest = $q->max('updated_at');
        return response()->json([
            'latest' => $latest ? (string) $latest : null,
        ]);
    }

    /** Lista las tareas archivadas (soft-deleted) con su proyecto y labels. */
    public function archived(): View
    {
        $tasks = Task::onlyTrashed()
            ->with(['project', 'labels'])
            ->orderByDesc('deleted_at')
            ->get();

        return view('tasks.archived', [
            'tasks' => $tasks,
        ]);
    }

    /** Restaura una tarea archivada. */
    public function restore(int $task): RedirectResponse
    {
        $t = Task::onlyTrashed()->findOrFail($task);
        $t->restore();
        // Al volver, dejamos status backlog para no resucitar en mitad de "Doing".
        if ($t->status === TaskStatus::Done) {
            // Si era Done, mantenemos completed_at; nada que tocar.
        }

        return redirect()->route('tasks.archived')->with('status', 'Tarea restaurada.');
    }

    /** Borra definitivamente una tarea ya archivada. */
    public function forceDestroy(int $task): RedirectResponse
    {
        $t = Task::onlyTrashed()->findOrFail($task);
        $t->forceDelete();

        return redirect()->route('tasks.archived')->with('status', 'Tarea borrada para siempre.');
    }

    /** Restaura en lote las tareas archivadas seleccionadas. */
    public function bulkRestore(Request $request): RedirectResponse
    {
        $ids = $this->validatedTaskIds($request);

        $n = Task::onlyTrashed()->whereIn('id', $ids)->restore();

        return redirect()->route('tasks.archived')
            ->with('status', $n === 1 ? 'Tarea restaurada.' : "{$n} tareas restauradas.");
    }

    /** Borra definitivamente en lote las tareas archivadas seleccionadas. */
    public function bulkForceDestroy(Request $request): RedirectResponse
    {
        $ids = $this->validatedTaskIds($request);

        // forceDelete() sobre la query no respeta SoftDeletes scope salvo
        // que partamos de onlyTrashed(); así solo tocamos archivadas.
        $n = Task::onlyTrashed()->whereIn('id', $ids)->forceDelete();

        return redirect()->route('tasks.archived')
            ->with('status', $n === 1 ? 'Tarea borrada para siempre.' : "{$n} tareas borradas para siempre.");
    }

    /** Valida el array `ids[]` de los endpoints en lote y lo devuelve. */
    private function validatedTaskIds(Request $request): array
    {
        return $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];
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
            'label_ids'   => ['nullable', 'array'],
            'label_ids.*' => ['integer', 'exists:task_labels,id'],
        ]);
    }
}
