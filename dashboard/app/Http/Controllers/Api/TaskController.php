<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * API REST · CRUD de tareas. Cliente principal previsto: la extensión
 * code-kanban (fork) que sincroniza un .todo.kanban por repo con esta
 * app. Auth por Bearer token estático (middleware `api.token`).
 *
 * Convenciones:
 *   - Las respuestas usan TaskResource (timestamps ISO 8601).
 *   - Los filtros se pasan en query string: ?project=, ?status=, ?since=.
 *   - El listado puede pedirse con ?include_archived=1 para ver también
 *     las soft-deleted (útil para sync inicial); por defecto solo activas.
 */
class TaskController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'project'          => ['nullable', 'integer'],
            'status'           => ['nullable', Rule::enum(TaskStatus::class)],
            'since'            => ['nullable', 'date'],
            'include_archived' => ['nullable', 'boolean'],
        ]);

        $q = Task::query()
            ->with(['project', 'labels', 'checkboxes', 'comments'])
            ->orderBy('position')
            ->orderBy('id');

        if (! empty($data['include_archived'])) {
            $q->withTrashed();
        }
        if (! empty($data['project'])) {
            $q->where('project_id', $data['project']);
        }
        if (! empty($data['status'])) {
            $q->where('status', $data['status']);
        }
        if (! empty($data['since'])) {
            $q->where('updated_at', '>=', CarbonImmutable::parse($data['since']));
        }

        return TaskResource::collection($q->get());
    }

    public function show(Task $task): TaskResource
    {
        $task->load(['project', 'labels', 'checkboxes', 'comments']);
        return new TaskResource($task);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateTask($request, isUpdate: false);
        $labels = $data['label_ids'] ?? [];
        unset($data['label_ids']);
        $data['position'] = (Task::where('status', $data['status'])->max('position') ?? -1) + 1;

        $task = Task::create($data);
        $task->labels()->sync($labels);
        $task->load(['project', 'labels', 'checkboxes', 'comments']);

        return (new TaskResource($task))->response()->setStatusCode(201);
    }

    public function update(Request $request, Task $task): TaskResource
    {
        $data = $this->validateTask($request, isUpdate: true);
        $labels = $data['label_ids'] ?? null;
        unset($data['label_ids']);

        $task->update([...$data, 'github_dirty' => true]);
        if (is_array($labels)) {
            $task->labels()->sync($labels);
        }
        $task->load(['project', 'labels', 'checkboxes', 'comments']);

        return new TaskResource($task);
    }

    /** Archiva (soft delete). 204 sin cuerpo, coherente con REST. */
    public function destroy(Task $task): JsonResponse
    {
        $task->delete();
        return response()->json(null, 204);
    }

    public function restore(int $task): TaskResource
    {
        $t = Task::onlyTrashed()->findOrFail($task);
        $t->restore();
        $t->load(['project', 'labels', 'checkboxes', 'comments']);
        return new TaskResource($t);
    }

    public function forceDestroy(int $task): JsonResponse
    {
        $t = Task::onlyTrashed()->findOrFail($task);
        $t->forceDelete();
        return response()->json(null, 204);
    }

    /**
     * Validación del payload. En update todos los campos son opcionales
     * excepto los que se envían; en store, `title` y `status` son requeridos.
     *
     * @return array<string,mixed>
     */
    private function validateTask(Request $request, bool $isUpdate): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'title'       => [$required, 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'status'      => [$required, Rule::enum(TaskStatus::class)],
            'priority'    => ['nullable', Rule::enum(TaskPriority::class)],
            'project_id'  => ['nullable', 'integer', 'exists:projects,id'],
            'due_date'    => ['nullable', 'date'],
            'label_ids'   => ['nullable', 'array'],
            'label_ids.*' => ['integer', 'exists:task_labels,id'],
        ]);
    }
}
