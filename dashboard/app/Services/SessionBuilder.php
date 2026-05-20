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
 *
 * Agrupacion: dos bloques contiguos forman la misma sesion si comparten
 * proyecto dominante y ambos son idle o ambos no-idle. El `status` concreto
 * (auto/edited/merged/split) NO rompe la sesion: asi un bloque reasignado a
 * mano se funde visualmente con sus vecinos auto del mismo proyecto.
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
            $blockIsIdle = $block->status === TimeBlock::STATUS_IDLE;

            $sameSession = $current !== null
                && $current['project_id'] === $block->dominant_project_id
                && $current['is_idle']    === $blockIsIdle
                && $block->starts_at->equalTo($current['ends_at']);

            if (! $sameSession) {
                if ($current) {
                    $sessions[] = $this->finalize($current, $tz);
                }
                $current = [
                    'project_id' => $block->dominant_project_id,
                    'project'    => $block->project,
                    'is_idle'    => $blockIsIdle,
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

        $status = $this->sessionStatus($blocks, $current['is_idle']);

        // Confianza: media de los bloques con confianza definida.
        $confidences = $blocks->pluck('confidence')->filter(fn ($c) => $c !== null);
        $confAvg = $confidences->isEmpty() ? null : (float) $confidences->avg();

        // Evidencia consolidada: todos los activity_events de los bloques.
        $evidence = $blocks
            ->flatMap(fn (TimeBlock $b) => $b->evidence)
            ->map(fn ($ev) => $ev->activityEvent)
            ->filter()
            ->unique('id')
            ->sortBy('occurred_at')
            ->values();

        $start = Carbon::parse($current['starts_at']);
        $end   = Carbon::parse($current['ends_at']);

        $summary = $this->buildSummary($status, $current['project'], $blocks, $evidence);

        return [
            'project'           => $current['project'],
            'status'            => $status,
            'is_idle'           => $current['is_idle'],
            'confidence'        => $confAvg,
            'confidence_label'  => $this->labelForConfidence($confAvg, $status),
            'starts_at_local'   => $start->copy()->setTimezone($tz),
            'ends_at_local'     => $end->copy()->setTimezone($tz),
            'duration_minutes'  => max(1, (int) $start->diffInMinutes($end)),
            'block_count'       => $blocks->count(),
            'block_ids'         => $blocks->pluck('id')->all(),
            'evidence'          => $evidence,
            'summary'           => $summary,
        ];
    }

    /**
     * Estado representativo de la sesion: idle si todos idle; editado si
     * algun bloque fue tocado a mano; si no, auto.
     */
    private function sessionStatus(Collection $blocks, bool $isIdle): string
    {
        if ($isIdle) {
            return TimeBlock::STATUS_IDLE;
        }
        $manual = [TimeBlock::STATUS_EDITED, TimeBlock::STATUS_MERGED, TimeBlock::STATUS_SPLIT];
        if ($blocks->contains(fn (TimeBlock $b) => in_array($b->status, $manual, true))) {
            return TimeBlock::STATUS_EDITED;
        }
        return TimeBlock::STATUS_AUTO;
    }

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
            $texts = $edited->pluck('text')->filter()->unique()->values();
            return $texts->implode(' ');
        }

        if (! $this->summaryGenerator) {
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
        if ($status === TimeBlock::STATUS_EDITED) {
            return 'editado';
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
