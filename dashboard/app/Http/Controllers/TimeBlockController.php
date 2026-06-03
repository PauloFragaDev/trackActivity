<?php

namespace App\Http\Controllers;

use App\Enums\BlockStatus;
use App\Enums\SummaryEngine;
use App\Models\GeneratedSummary;
use App\Models\ProjectMapping;
use App\Models\TimeBlock;
use App\Services\Aggregator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimeBlockController extends Controller
{
    /** Peso por defecto de una regla creada al corregir un bloque. */
    private const RULE_WEIGHT_BONUS = 5;

    public function __construct(private readonly Aggregator $aggregator) {}

    /**
     * Edicion manual de una sesion: reasigna el proyecto y/o sobrescribe el
     * resumen sobre todos los time_blocks que la componen.
     *
     * Los bloques quedan marcados como `edited`, de modo que el Aggregator
     * no los recomputa en los rebuilds (salvo --force-edited) y el
     * SummaryGenerator respeta el texto (edited_by_user=true).
     *
     * Bucle de aprendizaje: si llegan `create_mappings`, se crean reglas de
     * mapeo hacia el proyecto destino para que esa señal deje de atribuirse
     * mal. Con `reprocess_days` se reatribuyen los bloques `auto` recientes.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'block_ids'         => ['required', 'array', 'min:1'],
            'block_ids.*'       => ['integer'],
            'project_id'        => ['nullable', 'integer', 'exists:projects,id'],
            'summary_text'      => ['nullable', 'string', 'max:500'],
            'date'              => ['required', 'date_format:Y-m-d'],
            'create_mappings'   => ['nullable', 'array'],
            'create_mappings.*' => ['string', 'max:300'],
            'reprocess_days'    => ['nullable', 'integer', 'in:0,7,30'],
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

        // Bucle de aprendizaje: crear reglas + (opcional) reprocesar. Solo si
        // hay proyecto destino — mapear "a ningún proyecto" no tiene sentido.
        $rulesCreated = 0;
        if ($projectId !== null) {
            $rulesCreated = $this->createMappings($projectId, $data['create_mappings'] ?? []);
        }

        $reprocessDays = (int) ($data['reprocess_days'] ?? 0);
        if ($rulesCreated > 0 && $reprocessDays > 0) {
            $end   = CarbonImmutable::now('UTC');
            $start = $end->subDays($reprocessDays);
            $this->aggregator->rebuildRange($start, $end, forceEdited: false);
        }

        $msg = $summaryText !== null && $summaryText !== ''
            ? 'Sesión actualizada (proyecto y resumen).'
            : 'Proyecto de la sesión reasignado.';
        if ($rulesCreated > 0) {
            $msg .= " {$rulesCreated} regla(s) creada(s)"
                . ($reprocessDays > 0 ? " y bloques recientes recalculados." : '.');
        }

        return redirect()
            ->route('timeline.day', ['date' => $data['date']])
            ->with('status', $msg);
    }

    /**
     * Crea las reglas de mapeo elegidas (formato "type:pattern") hacia el
     * proyecto destino. Idempotente: no duplica un mapeo ya existente. Ignora
     * tipos desconocidos y patrones vacíos. Devuelve cuántas se crearon.
     *
     * @param  list<string>  $specs
     */
    private function createMappings(int $projectId, array $specs): int
    {
        $created = 0;

        foreach ($specs as $spec) {
            [$type, $pattern] = array_pad(explode(':', (string) $spec, 2), 2, '');
            $pattern = trim($pattern);

            if ($pattern === '' || ! in_array($type, ProjectMapping::TYPES, true)) {
                continue;
            }

            $mapping = ProjectMapping::firstOrCreate(
                ['project_id' => $projectId, 'type' => $type, 'pattern' => $pattern],
                [
                    'is_regex'     => false,
                    'enabled'      => true,
                    'weight_bonus' => self::RULE_WEIGHT_BONUS,
                    'origin'       => 'block_correction',
                ],
            );

            if ($mapping->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
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
