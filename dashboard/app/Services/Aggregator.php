<?php

namespace App\Services;

use App\Enums\BlockStatus;
use App\Models\ActivityEvent;
use App\Models\ManualEntry;
use App\Models\TimeBlock;
use App\Models\TimeBlockEvidence;
use App\Services\Scoring\Scorer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Agrupa activity_events en bloques alineados al grid (15 min por defecto) y
 * persiste time_blocks + time_block_evidence + scoring_snapshot.
 *
 * Reglas:
 *   - Idempotente: ejecutar varias veces sobre el mismo rango produce el mismo resultado.
 *   - No destructivo sobre bloques editados manualmente (status in [edited,merged,split])
 *     salvo que se pase forceEdited=true.
 *   - Todas las datetimes que toca son UTC en BBDD.
 */
class Aggregator
{
    public function __construct(
        private readonly Scorer $scorer,
        private readonly int $blockMinutes = 15,
    ) {}

    public static function fromConfig(Scorer $scorer): self
    {
        return new self($scorer, (int) config('tracker.block_minutes', 15));
    }

    /**
     * Reconstruye los bloques entre [$start, $end) en UTC.
     * Devuelve el numero de bloques creados/actualizados.
     */
    public function rebuildRange(CarbonImmutable $start, CarbonImmutable $end, bool $forceEdited = false): int
    {
        $start = $this->alignToGridFloor($start);
        $end   = $this->alignToGridCeil($end);

        $count = 0;
        $cursor = $start;
        while ($cursor->lt($end)) {
            $blockEnd = $cursor->add(CarbonInterval::minutes($this->blockMinutes));
            if ($this->rebuildOneBlock($cursor, $blockEnd, $forceEdited)) {
                $count++;
            }
            $cursor = $blockEnd;
        }
        return $count;
    }

    public function rebuildDay(CarbonImmutable $localDay, string $tz, bool $forceEdited = false): int
    {
        $startLocal = $localDay->setTimezone($tz)->startOfDay();
        $endLocal   = $startLocal->add(CarbonInterval::day());
        return $this->rebuildRange(
            $startLocal->setTimezone('UTC'),
            $endLocal->setTimezone('UTC'),
            $forceEdited,
        );
    }

    // ──────────────────────────────────────────────

    private function rebuildOneBlock(CarbonImmutable $start, CarbonImmutable $end, bool $forceEdited): bool
    {
        // Un tramo cubierto por una entrada manual no se auto-genera: la
        // entrada manual es la fuente de verdad para ese horario.
        if ($this->coveredByManualEntry($start, $end)) {
            return false;
        }

        $events = $this->eventsBetween($start, $end);
        $existing = TimeBlock::query()->where('starts_at', $start->format('Y-m-d H:i:s'))->first();

        if ($events->isEmpty()) {
            // No hay datos: si existe un bloque previo, lo dejamos en paz
            // (puede haber sido editado). Si no, no creamos nada.
            return false;
        }

        if ($existing && $existing->status->isManual() && ! $forceEdited) {
            return false;
        }

        $result = $this->scorer->score($events, $this->blockMinutes);

        $status = $result->isIdle ? BlockStatus::Idle : BlockStatus::Auto;

        DB::transaction(function () use ($start, $end, $result, $events, $status, $existing) {
            /** @var TimeBlock $block */
            $block = TimeBlock::updateOrCreate(
                ['starts_at' => $start->format('Y-m-d H:i:s')],
                [
                    'ends_at'             => $end->format('Y-m-d H:i:s'),
                    'dominant_project_id' => $result->dominantProjectId,
                    'confidence'          => $result->confidence,
                    'status'              => $status,
                    'scoring_snapshot'    => $result->snapshot(),
                    'generated_at'        => now('UTC'),
                ],
            );

            // Borrar evidencia previa y reescribir.
            TimeBlockEvidence::where('time_block_id', $block->id)->delete();

            foreach ($result->evidence as $ev) {
                TimeBlockEvidence::create([
                    'time_block_id'      => $block->id,
                    'activity_event_id'  => $ev['event_id'],
                    'weight_contributed' => $ev['weight'],
                    'note'               => $ev['signal_kind'] . ': ' . $ev['note'],
                ]);
            }
        });

        return true;
    }

    /** @return Collection<int,ActivityEvent> */
    private function eventsBetween(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return ActivityEvent::query()
            ->where('occurred_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<',  $end->format('Y-m-d H:i:s'))
            ->orderBy('occurred_at')
            ->get();
    }

    /** ¿Hay alguna entrada manual que solape el tramo [$start, $end)? */
    private function coveredByManualEntry(CarbonImmutable $start, CarbonImmutable $end): bool
    {
        return ManualEntry::query()
            ->where('starts_at', '<', $end->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $start->format('Y-m-d H:i:s'))
            ->exists();
    }

    private function alignToGridFloor(CarbonImmutable $dt): CarbonImmutable
    {
        $minutes = $dt->minute;
        $aligned = $minutes - ($minutes % $this->blockMinutes);
        return $dt->setMinute($aligned)->setSecond(0)->setMicrosecond(0);
    }

    private function alignToGridCeil(CarbonImmutable $dt): CarbonImmutable
    {
        $floor = $this->alignToGridFloor($dt);
        return $floor->equalTo($dt) ? $floor : $floor->add(CarbonInterval::minutes($this->blockMinutes));
    }
}
