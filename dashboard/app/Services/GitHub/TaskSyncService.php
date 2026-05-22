<?php

namespace App\Services\GitHub;

use App\Enums\TaskStatus;
use App\Models\Task;

/**
 * Sincroniza el tablero Kanban con un GitHub Project, en dos sentidos.
 *
 * Orden por sincronización: primero PUSH (sube los cambios locales) y
 * luego PULL (trae el resto). La resolución fina de conflictos llega en G3;
 * de momento, una tarea con cambios locales se sube tal cual.
 */
class TaskSyncService
{
    public function __construct(private readonly ProjectClient $client) {}

    /**
     * @return array{created:int,updated:int,removed:int,pushed:int}
     */
    public function sync(): array
    {
        $project   = $this->client->resolveProject();
        $items     = $this->client->listItems($project['id']);
        $statusMap = (array) config('github.status_map', []);   // local => nombre GitHub
        $toLocal   = array_flip($statusMap);                    // nombre GitHub => local

        $remoteById = [];
        foreach ($items as $it) {
            $remoteById[$it['id']] = $it;
        }

        $stats   = ['created' => 0, 'updated' => 0, 'removed' => 0, 'pushed' => 0];
        $pushed  = [];   // items recién subidos → el pull no los machaca
        $deleted = [];   // items borrados en este run → el pull no los recrea
        $synced  = [];   // items "en sync" → para detectar borrados remotos

        // ── PUSH ───────────────────────────────────────────────
        // 1. Borrados locales → eliminar el item remoto y purgar la fila.
        foreach (Task::onlyTrashed()->get() as $task) {
            if ($task->github_item_id !== null) {
                if (isset($remoteById[$task->github_item_id])) {
                    $this->client->deleteItem($project['id'], $task->github_item_id);
                }
                $deleted[] = $task->github_item_id;
            }
            $task->forceDelete();
        }

        // 2. Tareas nuevas (sin vínculo) → crear un draft item.
        foreach (Task::whereNull('github_item_id')->get() as $task) {
            $itemId = $this->client->createDraftItem(
                $project['id'], $task->title, (string) $task->description
            );
            $this->applyStatus($project, $itemId, $task->status->value, $statusMap);
            $this->markSynced($task, $itemId);
            $pushed[] = $itemId;
            $synced[] = $itemId;
            $stats['pushed']++;
        }

        // 3. Tareas con cambios locales → actualizar el item remoto.
        foreach (Task::whereNotNull('github_item_id')->where('github_dirty', true)->get() as $task) {
            $remote = $remoteById[$task->github_item_id] ?? null;
            if ($remote === null) {
                continue;   // el item ya no existe; el pull limpiará la tarea
            }
            if ($remote['isDraft'] && $remote['contentId'] !== null) {
                $this->client->updateDraftItem($remote['contentId'], $task->title, (string) $task->description);
            }
            $this->applyStatus($project, $task->github_item_id, $task->status->value, $statusMap);
            $this->markSynced($task, $task->github_item_id);
            $pushed[] = $task->github_item_id;
            $stats['pushed']++;
        }

        // ── PULL ───────────────────────────────────────────────
        foreach ($items as $item) {
            if (in_array($item['id'], $deleted, true)) {
                continue;   // borrado en este mismo run; no recrearlo
            }
            $synced[] = $item['id'];
            if (in_array($item['id'], $pushed, true)) {
                continue;   // recién subido por nosotros; no machacar
            }

            $localStatus = $toLocal[$item['status']] ?? null;
            $task        = Task::where('github_item_id', $item['id'])->first();

            if ($task === null) {
                $status = $localStatus ?? TaskStatus::Todo->value;
                $task   = Task::create([
                    'github_item_id' => $item['id'],
                    'title'          => $item['title'],
                    'description'    => $item['body'] ?: null,
                    'status'         => $status,
                    'position'       => (Task::where('status', $status)->max('position') ?? -1) + 1,
                ]);
                $stats['created']++;
            } else {
                $task->update([
                    'title'       => $item['title'],
                    'description' => $item['body'] ?: null,
                    'status'      => $localStatus ?? $task->status->value,
                ]);
                $stats['updated']++;
            }
            $this->markSynced($task, $item['id']);
        }

        // Items eliminados en GitHub → borrar la tarea local.
        if (! empty($synced)) {
            $stats['removed'] = Task::whereNotNull('github_item_id')
                ->whereNotIn('github_item_id', $synced)
                ->forceDelete();
        }

        return $stats;
    }

    /** Marca la tarea como sincronizada (sin cambios locales pendientes). */
    private function markSynced(Task $task, string $itemId): void
    {
        Task::whereKey($task->getKey())->update([
            'github_item_id'   => $itemId,
            'github_synced_at' => now(),
            'github_dirty'     => false,
        ]);
    }

    /** Fija en GitHub el estado de un item según el mapa de columnas. */
    private function applyStatus(array $project, string $itemId, string $localStatus, array $statusMap): void
    {
        $optionName = $statusMap[$localStatus] ?? null;
        $optionId   = $optionName !== null ? ($project['statusOptions'][$optionName] ?? null) : null;

        if ($project['statusFieldId'] !== null && $optionId !== null) {
            $this->client->setItemStatus($project['id'], $itemId, $project['statusFieldId'], $optionId);
        }
    }
}
