<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskLabel;
use App\Services\GitHub\ProjectClient;
use App\Services\GitHub\TaskSyncService;
use Carbon\CarbonImmutable;
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
    public function index(Request $request, ProjectClient $github): View
    {
        $projectId = $request->integer('project') ?: null;
        $priority  = $request->input('priority') ?: null;

        $tasks = Task::with(['project', 'manualEntries', 'labels', 'checkboxes', 'comments'])
            ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
            ->when($priority, fn ($q) => $q->where('priority', $priority))
            ->orderBy('position')
            ->get()
            ->groupBy(fn (Task $t) => $t->status->value);

        $lastSync = Task::max('github_synced_at');

        return view('tasks.board', [
            'columns'    => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'tasks'      => $tasks,
            'projects'   => Project::orderBy('code')->get(),
            'labels'     => TaskLabel::orderBy('position')->orderBy('title')->get(),
            'projectId'  => $projectId,
            'priority'   => $priority,
            'githubSync' => $github->isConfigured(),
            'lastSync'   => $lastSync ? CarbonImmutable::parse($lastSync) : null,
        ]);
    }

    /** Sincroniza el tablero con el GitHub Project (botón "Sincronizar"). */
    public function sync(ProjectClient $github, TaskSyncService $service): RedirectResponse
    {
        if (! $github->isConfigured()) {
            return back()->with('status', 'La sincronización con GitHub no está configurada.');
        }

        try {
            $r = $service->sync();
        } catch (\Throwable $e) {
            return back()->with('status', 'No se pudo sincronizar: ' . $e->getMessage());
        }

        return back()->with('status', "Sincronizado con GitHub — subidas: {$r['pushed']}, "
            . "creadas: {$r['created']}, actualizadas: {$r['updated']}, "
            . "eliminadas: {$r['removed']}, conflictos: {$r['conflicts']}.");
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTask($request);
        $labelIds = $data['label_ids'] ?? [];
        unset($data['label_ids']);
        $data['position'] = (Task::where('status', $data['status'])->max('position') ?? -1) + 1;

        $task = Task::create($data);
        $task->labels()->sync($labelIds);

        return redirect()->route('tasks.index')->with('status', 'Tarea creada.');
    }

    public function update(Request $request, Task $task): RedirectResponse
    {
        $data = $this->validateTask($request);
        $labelIds = $data['label_ids'] ?? [];
        unset($data['label_ids']);

        $task->update([...$data, 'github_dirty' => true]);
        $task->labels()->sync($labelIds);

        return redirect()->route('tasks.index')->with('status', 'Tarea actualizada.');
    }

    /**
     * "Archivar" — soft delete. La tarea sale del board pero puede recuperarse
     * desde /tasks/archived. Para borrado definitivo, ver forceDestroy().
     */
    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return redirect()->route('tasks.index')->with('status', 'Tarea archivada.');
    }

    /**
     * Canal SSE para la página /tasks. Emite "change" cada vez que cambia
     * el MAX(updated_at) de las tasks (incluye soft-deleted vía
     * withTrashed). El cliente JS recarga el board al recibir el evento.
     *
     * Mismo diseño que `Api\KanbanStreamController` (poll 1 s, heartbeat
     * 15 s, rotación 60 s), pero servido por la sesión web — single-user,
     * sin auth porque la app no la tiene (bind a 127.0.0.1).
     */
    public function stream(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $projectId = $request->integer('project') ?: null;

        return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($projectId) {
            @set_time_limit(0);
            ignore_user_abort(false);
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            $startedAt = time();
            $nextHeartbeat = $startedAt + 15;
            $latest = $this->latestUpdatedAt($projectId);

            echo "event: hello\n";
            echo 'data: ' . json_encode(['latest' => $latest, 'rotate_after_seconds' => 60]) . "\n\n";
            @flush();

            while (true) {
                if (connection_aborted()) {
                    break;
                }
                if (time() - $startedAt >= 60) {
                    echo "event: rotate\ndata: {\"reason\":\"max_duration\"}\n\n";
                    @flush();
                    break;
                }

                $current = $this->latestUpdatedAt($projectId);
                if ($current !== null && $current !== $latest) {
                    echo "event: change\n";
                    echo 'data: ' . json_encode(['latest' => $current]) . "\n\n";
                    @flush();
                    $latest = $current;
                    $nextHeartbeat = time() + 15;
                } elseif (time() >= $nextHeartbeat) {
                    echo ": heartbeat\n\n";
                    @flush();
                    $nextHeartbeat = time() + 15;
                }
                sleep(1);
            }
        }, 200, [
            'Content-Type'      => 'text/event-stream; charset=utf-8',
            'Cache-Control'     => 'no-cache, no-transform',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** MAX(updated_at) global o filtrado por proyecto. */
    private function latestUpdatedAt(?int $projectId): ?string
    {
        $q = Task::withTrashed();
        if ($projectId) {
            $q->where('project_id', $projectId);
        }
        $value = $q->max('updated_at');
        return $value ? (string) $value : null;
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

    /** Mueve una tarea de columna / posición (endpoint AJAX del drag & drop). */
    public function move(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'status'   => ['required', Rule::enum(TaskStatus::class)],
            'position' => ['required', 'integer', 'min:0'],
        ]);

        $oldStatus = $task->status->value;

        $task->status = TaskStatus::from($data['status']);
        $task->github_dirty = true;
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
