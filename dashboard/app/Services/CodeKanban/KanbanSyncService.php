<?php

namespace App\Services\CodeKanban;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectMapping;
use App\Models\Task;
use App\Models\TaskLabel;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Sincronización trackActivity ↔ extensión code-kanban.
 *
 * Modelo: el cliente (la extensión) envía el estado completo del
 * `.todo.kanban` de un workspace concreto, identificado por el path
 * local. El server hace merge por tarjeta usando `kanban_card_id`
 * como ancla estable y `updated_at` como criterio de conflicto
 * (last-writer-wins). El server devuelve el estado resuelto: el
 * cliente reescribe su archivo con lo que reciba.
 *
 * Reglas
 *   · Tarjeta nueva (sin kanban_card_id en server) → crear task.
 *   · Tarjeta ya enlazada → comparar updated_at:
 *       - cliente más reciente → server adopta cambios del cliente.
 *       - server  más reciente → respuesta lleva la versión server.
 *   · Tarjeta en server con `kanban_card_id` del proyecto que YA NO está
 *     en el payload → archivada (soft delete).
 *   · Labels: matching por título (case-insensitive). Si no existe en
 *     server, se crea con el color del cliente o un gris por defecto.
 *   · Columnas: el server fija el set (TaskStatus enum). El cliente
 *     debe enviar lists con los 6 títulos. Si llega uno desconocido,
 *     intentamos un mapping aproximado; si no, se ignoran sus cards
 *     (errores agregados en la respuesta).
 */
class KanbanSyncService
{
    /**
     * @param string $workspacePath  Path absoluto del workspace abierto en VS Code.
     * @param CarbonImmutable $clientUpdatedAt  Cuándo fue la última edición del archivo.
     * @param list<array{title:string,cards:list<array>}> $lists  Payload del .todo.kanban.
     * @return array  { project, applied_at, lists, errors, stats }
     */
    public function sync(string $workspacePath, CarbonImmutable $clientUpdatedAt, array $lists): array
    {
        $project = $this->resolveProject($workspacePath);
        if (! $project) {
            return [
                'error'   => 'no_project_mapping',
                'message' => "No hay ProjectMapping (type folder/repository) para «{$workspacePath}». "
                    . "Configura un mapping en /projects antes de sincronizar.",
            ];
        }

        $errors = [];
        $stats  = ['created' => 0, 'updated_local' => 0, 'kept_server' => 0, 'archived' => 0];
        $seenCardIds = [];

        DB::transaction(function () use ($project, $clientUpdatedAt, $lists, &$stats, &$errors, &$seenCardIds) {
            foreach ($lists as $list) {
                $status = $this->resolveStatus($list['title'] ?? '');
                if (! $status) {
                    $errors[] = "Columna desconocida: «{$list['title']}» — sus tarjetas se ignoraron.";
                    continue;
                }

                foreach ($list['cards'] ?? [] as $position => $cardData) {
                    $cardId = (string) ($cardData['id'] ?? '');
                    if ($cardId === '') {
                        $errors[] = "Tarjeta sin id: «" . ($cardData['title'] ?? '?') . "» — ignorada.";
                        continue;
                    }
                    $seenCardIds[] = $cardId;

                    $task = Task::where('kanban_card_id', $cardId)->first();

                    if (! $task) {
                        $this->createFromCard($project, $status, (int) $position, $cardId, $cardData);
                        $stats['created']++;
                        continue;
                    }

                    // Comparación por timestamp: gana el más reciente.
                    $clientCardAt = isset($cardData['updated_at'])
                        ? CarbonImmutable::parse($cardData['updated_at'])
                        : $clientUpdatedAt;

                    $serverAt = CarbonImmutable::parse($task->updated_at ?? '1970-01-01');
                    if ($clientCardAt->gt($serverAt)) {
                        $this->updateFromCard($task, $project, $status, (int) $position, $cardData);
                        $stats['updated_local']++;
                    } else {
                        $stats['kept_server']++;
                    }
                }
            }

            // Tarjetas del proyecto que estaban enlazadas y ya no están →
            // archivar. (No las borramos: si fue un error en el cliente, el
            // usuario puede restaurarlas desde /tasks/archived.)
            //
            // PROTECCIÓN: si el cliente envía un estado MÁS VIEJO que la
            // última sync server-side (típico cuando el usuario abrió VS
            // Code después de haber añadido cards en /tasks), no archivamos
            // nada — son tarjetas del server que el cliente todavía no ha
            // recibido. El cliente las verá en la respuesta y las adoptará.
            $latestServerSync = Task::where('project_id', $project->id)
                ->whereNotNull('kanban_synced_at')
                ->max('kanban_synced_at');

            $clientIsBehind = $latestServerSync !== null
                && $clientUpdatedAt->lt(CarbonImmutable::parse($latestServerSync));

            if (! $clientIsBehind) {
                $orphans = Task::where('project_id', $project->id)
                    ->whereNotNull('kanban_card_id')
                    ->whereNotIn('kanban_card_id', $seenCardIds)
                    ->get();
                foreach ($orphans as $orphan) {
                    $orphan->delete();
                    $stats['archived']++;
                }
            } else {
                $missing = Task::where('project_id', $project->id)
                    ->whereNotNull('kanban_card_id')
                    ->whereNotIn('kanban_card_id', $seenCardIds)
                    ->count();
                if ($missing > 0) {
                    $errors[] = "Cliente atrasado respecto al server ({$missing} tarjeta(s) del server "
                        . "no estaban en el payload). No se archiva nada — el cliente debe adoptar el estado server.";
                }
            }
        });

        return [
            'project' => [
                'id'    => $project->id,
                'code'  => $project->code,
                'name'  => $project->name,
                'color' => $project->color,
            ],
            'applied_at' => now()->toIso8601String(),
            'lists'      => $this->serverState($project),
            'errors'     => $errors,
            'stats'      => $stats,
        ];
    }

    /**
     * Resuelve el proyecto a partir del workspace_path. Primero busca un
     * mapping `folder` cuyo `pattern` sea exacto o sufijo del path; si no,
     * un mapping `repository` cuyo `pattern` sea el basename.
     *
     * Público porque el endpoint SSE de stream también necesita resolverlo.
     */
    public function resolveProject(string $workspacePath): ?Project
    {
        $normalized = rtrim($workspacePath, "/\\");

        // Folder: pattern es path (puede ser sufijo, ej. "/Projects/jasper-api"
        // matchea "/home/user/Projects/jasper-api").
        $byFolder = ProjectMapping::query()
            ->where('type', 'folder')
            ->where('enabled', true)
            ->get()
            ->first(function (ProjectMapping $m) use ($normalized) {
                $p = rtrim($m->pattern, "/\\");
                return $p !== '' && (
                    $p === $normalized
                    || str_ends_with($normalized, $p)
                    || str_starts_with($normalized, $p)
                );
            });
        if ($byFolder) {
            return $byFolder->project;
        }

        // Repository: pattern es nombre del repo. Comparamos contra el
        // basename del workspace_path.
        $basename = basename($normalized);
        $byRepo = ProjectMapping::query()
            ->where('type', 'repository')
            ->where('enabled', true)
            ->whereRaw('LOWER(pattern) = LOWER(?)', [$basename])
            ->first();

        return $byRepo?->project;
    }

    /** Mapea el título de la columna (cualquier capitalización) a TaskStatus. */
    private function resolveStatus(string $listTitle): ?TaskStatus
    {
        $needle = strtolower(trim($listTitle));
        foreach (TaskStatus::cases() as $case) {
            if (strtolower($case->label()) === $needle) {
                return $case;
            }
            // Aliases tolerantes (por si la extensión usa snake o sin espacio).
            $alt = strtolower(str_replace(' ', '', $case->label()));
            if ($alt === str_replace(' ', '', $needle)) {
                return $case;
            }
        }
        return null;
    }

    private function createFromCard(Project $project, TaskStatus $status, int $position, string $cardId, array $cardData): Task
    {
        $task = Task::create([
            'project_id'       => $project->id,
            'title'            => mb_substr((string) ($cardData['title'] ?? 'Sin título'), 0, 200),
            'description'      => $cardData['description'] ?? null,
            'status'           => $status->value,
            'due_date'         => $cardData['due_date'] ?? null,
            'position'         => $position,
            'kanban_card_id'   => $cardId,
            'kanban_synced_at' => now(),
        ]);
        $this->syncLabelsFromCard($task, $cardData);
        return $task;
    }

    private function updateFromCard(Task $task, Project $project, TaskStatus $status, int $position, array $cardData): void
    {
        $task->fill([
            'title'            => mb_substr((string) ($cardData['title'] ?? $task->title), 0, 200),
            'description'      => $cardData['description'] ?? null,
            'status'           => $status->value,
            'due_date'         => $cardData['due_date'] ?? null,
            'position'         => $position,
            // El project_id puede cambiar si el usuario mueve la card a otro repo;
            // por ahora respetamos el proyecto del workspace.
            'project_id'       => $project->id,
            'kanban_synced_at' => now(),
        ])->save();
        $this->syncLabelsFromCard($task, $cardData);
    }

    /**
     * Sincroniza las labels de la tarea con las del cliente. Match por título
     * (case-insensitive). Crea las que falten.
     */
    private function syncLabelsFromCard(Task $task, array $cardData): void
    {
        $clientLabels = $cardData['labels'] ?? [];
        if (! is_array($clientLabels) || empty($clientLabels)) {
            $task->labels()->sync([]);
            return;
        }

        $ids = [];
        foreach ($clientLabels as $l) {
            $title = trim((string) ($l['title'] ?? ''));
            if ($title === '') continue;
            $color = $this->normalizeColor($l['color'] ?? null);

            $label = TaskLabel::whereRaw('LOWER(title) = LOWER(?)', [$title])->first();
            if (! $label) {
                $maxPos = (int) TaskLabel::max('position');
                $label = TaskLabel::create([
                    'title'    => mb_substr($title, 0, 80),
                    'color'    => $color,
                    'position' => $maxPos + 1,
                ]);
            }
            $ids[] = $label->id;
        }
        $task->labels()->sync($ids);
    }

    private function normalizeColor(?string $color): string
    {
        if (! $color) return '#9CA3AF';
        $c = strtolower(trim($color));
        return preg_match('/^#[0-9a-f]{6}$/i', $c) ? $c : '#9CA3AF';
    }

    /**
     * Estado actual del proyecto serializado en el mismo formato que el
     * payload del cliente, para que la extensión reescriba su archivo
     * tras la sync.
     *
     * @return list<array{title:string,cards:list<array>}>
     */
    private function serverState(Project $project): array
    {
        $tasks = Task::where('project_id', $project->id)
            ->whereNotNull('kanban_card_id')
            ->with(['labels'])
            ->orderBy('position')
            ->get();

        $byStatus = $tasks->groupBy(fn (Task $t) => $t->status->value);

        $out = [];
        foreach (TaskStatus::cases() as $status) {
            $cards = ($byStatus[$status->value] ?? collect())
                ->values()
                ->map(fn (Task $t) => [
                    'id'          => $t->kanban_card_id,
                    'title'       => $t->title,
                    'description' => $t->description,
                    'due_date'    => $t->due_date?->format('Y-m-d'),
                    'labels'      => $t->labels->map(fn (TaskLabel $l) => [
                        'title' => $l->title,
                        'color' => $l->color,
                    ])->values()->all(),
                    'updated_at'  => $t->updated_at?->toIso8601String(),
                ])
                ->all();
            $out[] = ['title' => $status->label(), 'cards' => $cards];
        }
        return $out;
    }
}
