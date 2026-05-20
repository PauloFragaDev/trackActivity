<?php

namespace App\Http\Controllers;

use App\Enums\BlockStatus;
use App\Enums\SummaryEngine;
use App\Models\GeneratedSummary;
use App\Models\TimeBlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeBlockController extends Controller
{
    /**
     * Edicion manual de una sesion: reasigna el proyecto y/o sobrescribe el
     * resumen sobre todos los time_blocks que la componen.
     *
     * Los bloques quedan marcados como `edited`, de modo que el Aggregator
     * no los recomputa en los rebuilds (salvo --force-edited) y el
     * SummaryGenerator respeta el texto (edited_by_user=true).
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'block_ids'    => ['required', 'array', 'min:1'],
            'block_ids.*'  => ['integer'],
            'project_id'   => ['nullable', 'integer', 'exists:projects,id'],
            'summary_text' => ['nullable', 'string', 'max:500'],
            'date'         => ['required', 'date_format:Y-m-d'],
        ]);

        $blocks = TimeBlock::query()->whereIn('id', $data['block_ids'])->get();

        if ($blocks->isEmpty()) {
            return back()->with('status', 'No se encontraron bloques para editar.');
        }

        $projectId   = $data['project_id'] ?? null;
        $summaryText = isset($data['summary_text']) ? trim($data['summary_text']) : null;

        DB::transaction(function () use ($blocks, $projectId, $summaryText) {
            foreach ($blocks as $block) {
                // Un bloque idle no se reasigna a proyecto ni cambia de
                // estado/confianza/resumen: queda intacto.
                if ($block->status === BlockStatus::Idle) {
                    continue;
                }

                $block->update([
                    'dominant_project_id' => $projectId,
                    // El usuario es la autoridad: confianza plena.
                    'confidence'          => 1.0,
                    'status'              => BlockStatus::Edited,
                ]);

                if ($summaryText !== null && $summaryText !== '') {
                    $existing = $block->summary;
                    GeneratedSummary::updateOrCreate(
                        ['time_block_id' => $block->id],
                        [
                            'text'           => $summaryText,
                            // Conserva el engine original si existia; si no, 'manual'.
                            'engine'         => $existing->engine ?? SummaryEngine::Manual,
                            'edited_by_user' => true,
                            'generated_at'   => now('UTC'),
                        ],
                    );
                }
            }
        });

        $msg = $summaryText !== null && $summaryText !== ''
            ? 'Sesión actualizada (proyecto y resumen).'
            : 'Proyecto de la sesión reasignado.';

        return redirect()
            ->route('timeline.day', ['date' => $data['date']])
            ->with('status', $msg);
    }

    /**
     * Vuelve a poner una sesion en modo automatico: status=auto y borra el
     * summary editado. El siguiente rebuild la recalculara desde cero.
     */
    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'block_ids'   => ['required', 'array', 'min:1'],
            'block_ids.*' => ['integer'],
            'date'        => ['required', 'date_format:Y-m-d'],
        ]);

        DB::transaction(function () use ($data) {
            $blocks = TimeBlock::query()->whereIn('id', $data['block_ids'])->get();
            foreach ($blocks as $block) {
                if ($block->status === BlockStatus::Idle) {
                    continue;
                }
                $block->update(['status' => BlockStatus::Auto]);
                $block->summary?->update(['edited_by_user' => false]);
            }
        });

        return redirect()
            ->route('timeline.day', ['date' => $data['date']])
            ->with('status', 'Sesión devuelta a modo automático. Ejecuta rebuild para recalcularla.');
    }
}
