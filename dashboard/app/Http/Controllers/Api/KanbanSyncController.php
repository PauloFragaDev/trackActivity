<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\CodeKanban\KanbanSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API REST · sync trackActivity ↔ extensión code-kanban.
 *
 * POST /api/sync/kanban  — recibe el .todo.kanban completo de un
 * workspace y devuelve el estado resuelto.
 *
 * Auth: middleware `api.token` (igual que el resto de /api/*).
 */
class KanbanSyncController extends Controller
{
    public function __construct(private readonly KanbanSyncService $service) {}

    public function store(Request $request): JsonResponse
    {
        if (! Setting::get('sync.extension', true)) {
            return response()->json(['error' => 'La sincronización con la extensión está desactivada en Configuración.'], 403);
        }

        $data = $request->validate([
            'workspace_path'      => ['required', 'string', 'max:1024'],
            'client_updated_at'   => ['required', 'date'],
            'lists'               => ['required', 'array'],
            'lists.*.title'       => ['required', 'string', 'max:80'],
            'lists.*.cards'       => ['nullable', 'array'],
            'lists.*.cards.*.id'  => ['required_with:lists.*.cards.*', 'string', 'max:120'],
            'lists.*.cards.*.title'       => ['nullable', 'string', 'max:200'],
            'lists.*.cards.*.description' => ['nullable', 'string'],
            'lists.*.cards.*.due_date'    => ['nullable', 'date'],
            'lists.*.cards.*.updated_at'  => ['nullable', 'date'],
            'lists.*.cards.*.labels'      => ['nullable', 'array'],
            'lists.*.cards.*.labels.*.title' => ['required_with:lists.*.cards.*.labels.*', 'string', 'max:80'],
            'lists.*.cards.*.labels.*.color' => ['nullable', 'string', 'max:16'],
        ]);

        $result = $this->service->sync(
            $data['workspace_path'],
            CarbonImmutable::parse($data['client_updated_at']),
            $data['lists'],
        );

        if (isset($result['error']) && $result['error'] === 'no_project_mapping') {
            return response()->json($result, 422);
        }

        return response()->json($result, 200);
    }
}
