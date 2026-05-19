<?php

namespace App\Services;

use App\Models\TimeBlock;
use App\Services\Summaries\SummaryGenerator;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Lee bloques pre-computados de `time_blocks` y los agrupa visualmente en
 * "sesiones": bloques contiguos del mismo proyecto.
 *
 * El scoring real lo hace el Aggregator (M3). Esta clase solo presenta.
 * Adicionalmente sintetiza un resumen por sesion combinando la evidencia
 * de todos sus bloques.
 */
class SessionBuilder
{
    public function __construct(
        private readonly int $idleGapMinutes,
        private readonly ?SummaryGenerator $summaryGenerator = null,
    ) {}

    public static function fromConfig(?SummaryGenerator $generator = null): self
    {
        return new self(
            idleGapMinutes: (int) config('tracker.idle_gap_minutes', 5),
            summaryGenerator: $generator,
        );
    }

    /**
     * @return list<array{
     *   project: \App\Models\Project|null,
     *   status: string,
     *   confidence: ?float,
     *   confidence_label: string,
     *   starts_at_local: \Carbon\Carbon,
     *   ends_at_local: \Carbon\Carbon,
     *   duration_minutes: int,
     *   block_count: int,
     *   evidence: \Illuminate\Support\Collection,
     * }>
     */
    public function buildForDay(CarbonImmutable $localDay): array
    {
        $tz = $this->displayTz();
        $startLoc = $localDay->setTimezone($tz)->startOfDay();
        $endLoc   = $startLoc->addDay();
        $startUtc = $startLoc->setTimezone('UTC');
        $endUtc   = $endLoc->setTimezone('UTC');

        $blocks = TimeBlock::query()
            ->with(['project', 'summary', 'evidence.activityEvent'])
            ->where('starts_at', '>=', $startUtc->format('Y-m-d H:i:s'))
            ->where('starts_at', '<',  $endUtc->format('Y-m-d H:i:s'))
            ->orderBy('starts_at')
            ->get();

        if ($blocks->isEmpty()) {
            return [];
        }

        $sessions = [];
        $current  = null;

        foreach ($blocks as $block) {
            $sameSession = $current !== null
                && $current['project_id'] === $block->dominant_project_id
                && $current['status']     === $block->status
                && $block->starts_at->equalTo($current['ends_at']);

            if (! $sameSession) {
                if ($current) {
                    $sessions[] = $this->finalize($current, $tz);
                }
                $current = [
                    'project_id' => $block->dominant_project_id,
                    'project'    => $block->project,
                    'status'     => $block->status,
                    'starts_at'  => $block->starts_at->copy(),
                    'ends_at'    => $block->ends_at->copy(),
                    'blocks'     => collect([$block]),
                ];
                continue;
            }

            $current['ends_at'] = $block->ends_at->copy();
            $current['blocks']->push($block);
        }
        if ($current) {
            $sessions[] = $this->finalize($current, $tz);
        }

        return $sessions;
    }

    private function finalize(array $current, string $tz): array
    {
        /** @var Collection<int,TimeBlock> $blocks */
        $blocks = $current['blocks'];

        // Confianza: media de los bloques que tienen confianza definida.
        $confidences = $blocks->pluck('confidence')->filter(fn ($c) => $c !== null);
        $confAvg = $confidences->isEmpty() ? null : (float) $confidences->avg();

        // Evidencia consolidada: todos los activity_events de los bloques agrupados.
        $evidence = $blocks
            ->flatMap(fn (TimeBlock $b) => $b->evidence)
            ->map(fn ($ev) => $ev->activityEvent)
            ->filter()
            ->unique('id')
            ->sortBy('occurred_at')
            ->values();

        $start = Carbon::parse($current['starts_at']);
        $end   = Carbon::parse($current['ends_at']);

        $summary = $this->buildSummary($current['status'], $current['project'], $blocks, $evidence);

        return [
            'project'           => $current['project'],
            'status'            => $current['status'],
            'confidence'        => $confAvg,
            'confidence_label'  => $this->labelForConfidence($confAvg, $current['status']),
            'starts_at_local'   => $start->copy()->setTimezone($tz),
            'ends_at_local'     => $end->copy()->setTimezone($tz),
            'duration_minutes'  => max(1, (int) $start->diffInMinutes($end)),
            'block_count'       => $blocks->count(),
            'evidence'          => $evidence,
            'summary'           => $summary,
        ];
    }

    /**
     * Texto resumen para la sesion. Estrategia:
     *   1. Si hay un solo bloque con summary editado por usuario, lo usa tal cual.
     *   2. Si hay summaries persistidos no editados, sintetiza uno via generator
     *      sobre la evidencia consolidada.
     *   3. Si no hay generator inyectado, fallback texto vacio.
     */
    private function buildSummary(string $status, ?\App\Models\Project $project, Collection $blocks, Collection $evidence): ?string
    {
        if ($status === TimeBlock::STATUS_IDLE) {
            return null;
        }

        // Si algun bloque tiene un summary editado a mano, respetalo.
        $edited = $blocks
            ->map(fn (TimeBlock $b) => $b->summary)
            ->filter(fn ($s) => $s && $s->edited_by_user);
        if ($edited->isNotEmpty()) {
            // Concatena summaries editados unicos
            $texts = $edited->pluck('text')->filter()->unique()->values();
            return $texts->implode(' ');
        }

        if (! $this->summaryGenerator) {
            // Fallback: usar el text persistido del primer bloque, si existe
            $first = $blocks->first(fn ($b) => $b->summary && $b->summary->text);
            return $first?->summary->text;
        }

        return $this->summaryGenerator->renderText($project, $evidence);
    }

    private function labelForConfidence(?float $confidence, string $status): string
    {
        if ($status === TimeBlock::STATUS_IDLE) {
            return 'idle';
        }
        if ($confidence === null) {
            return 'n/a';
        }
        $cfg = config('tracker.confidence');
        return match (true) {
            $confidence >= $cfg['high']   => 'Alta',
            $confidence >= $cfg['medium'] => 'Media',
            default                        => 'Baja',
        };
    }

    private function displayTz(): string
    {
        return config('tracker.display_timezone', 'UTC');
    }
}
