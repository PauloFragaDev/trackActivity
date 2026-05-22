<?php

namespace App\Services\GitHub;

use App\Enums\TaskStatus;
use App\Models\Task;

/**
 * Sincroniza el tablero Kanban con un GitHub Project.
 *
 * G1: solo trae de GitHub (crear/actualizar/eliminar tareas locales).
 * El push y la resolución de conflictos llegan en G2 y G3.
 */
class TaskSyncService
{
    public function __construct(private readonly ProjectClient $client) {}

    /**
     * @return array{created:int,updated:int,removed:int}
     */
    public function sync(): array
    {
        $project   = $this->client->resolveProject();
        $items     = $this->client->listItems($project['id']);
        $statusMap = array_flip((array) config('github.status_map', []));   // nombre GitHub => TaskStatus local

        $created = 0;
        $updated = 0;
        $seen    = [];

        foreach ($items as $item) {
            $seen[]      = $item['id'];
            $localStatus = $statusMap[$item['status']] ?? null;
            $task        = Task::where('github_item_id', $item['id'])->first();

            if ($task === null) {
                $status = $localStatus ?? TaskStatus::Todo->value;
                Task::create([
                    'github_item_id'   => $item['id'],
                    'title'            => $item['title'],
                    'description'      => $item['body'] ?: null,
                    'status'           => $status,
                    'position'         => (Task::where('status', $status)->max('position') ?? -1) + 1,
                    'github_synced_at' => now(),
                ]);
                $created++;
            } else {
                $task->update([
                    'title'            => $item['title'],
                    'description'      => $item['body'] ?: null,
                    'status'           => $localStatus ?? $task->status->value,
                    'github_synced_at' => now(),
                ]);
                $updated++;
            }
        }

        // Items eliminados en GitHub → se borran localmente. Solo si la
        // respuesta trajo algún item (no borrar todo ante una respuesta vacía).
        $removed = 0;
        if (! empty($seen)) {
            $removed = Task::whereNotNull('github_item_id')
                ->whereNotIn('github_item_id', $seen)
                ->delete();
        }

        return ['created' => $created, 'updated' => $updated, 'removed' => $removed];
    }
}
