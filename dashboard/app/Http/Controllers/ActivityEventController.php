<?php

namespace App\Http\Controllers;

use App\Models\ActivityEvent;
use App\Services\Aggregator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Edición manual de un activity_event desde el timeline (lista de evidencia).
 * Por ahora solo se permite asignar/quitar el proyecto: el Scorer le da un
 * peso enorme y el bloque que contiene el evento queda atribuido a él.
 */
class ActivityEventController extends Controller
{
    public function update(Request $request, ActivityEvent $activityEvent, Aggregator $aggregator): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
        ]);

        $activityEvent->update(['project_id' => $data['project_id'] ?? null]);

        // Reconstruye el bloque que contiene el evento — la atribución se
        // refresca al instante. forceEdited=true: la edición manual del
        // usuario manda incluso sobre bloques marcados como editados.
        $when = CarbonImmutable::parse($activityEvent->occurred_at);
        $aggregator->rebuildRange(
            $when->subMinutes(1),
            $when->addMinutes(15),
            forceEdited: true,
        );

        $activityEvent->refresh()->load('project');

        return response()->json([
            'id'           => $activityEvent->id,
            'project_id'   => $activityEvent->project_id,
            'project_code' => $activityEvent->project?->code,
        ]);
    }
}
