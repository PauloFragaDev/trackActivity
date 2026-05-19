<?php

namespace App\Services\Scoring;

use App\Models\ActivityEvent;
use Illuminate\Support\Collection;

/**
 * Suma contribuciones de cada evento dentro de un bloque y determina el
 * proyecto dominante + confianza + evidencia trazable.
 *
 * No persiste nada; el Aggregator se encarga del IO.
 */
class Scorer
{
    /** Porcentaje minimo de tiempo en idle para marcar el bloque como idle. */
    public const IDLE_BLOCK_THRESHOLD = 0.8;

    public function __construct(
        private readonly MappingResolver $resolver,
    ) {}

    /**
     * @param Collection<int,ActivityEvent> $events  eventos crudos del bloque
     * @param int                            $blockDurationMinutes  para detectar idle
     */
    public function score(Collection $events, int $blockDurationMinutes = 15): ScoringResult
    {
        if ($events->isEmpty()) {
            return ScoringResult::empty();
        }

        if ($this->isDominantlyIdle($events, $blockDurationMinutes)) {
            return ScoringResult::idle();
        }

        /** @var array<int,int> project_id => total */
        $scores = [];

        /** @var array<int, array<int, array{event_id:int,weight:int,signal_kind:string,note:string}>> */
        $contribsByProject = [];

        foreach ($events as $event) {
            if ($event->source === ActivityEvent::SOURCE_IDLE) {
                continue;
            }

            $contributions = $this->resolver->contributionsFor($event);
            foreach ($contributions as $c) {
                if ($c['weight'] <= 0) {
                    continue;
                }
                $pid = $c['project_id'];
                $scores[$pid] = ($scores[$pid] ?? 0) + $c['weight'];

                $contribsByProject[$pid] ??= [];
                $contribsByProject[$pid][] = [
                    'event_id'    => (int) $event->id,
                    'weight'      => $c['weight'],
                    'signal_kind' => $c['signal_kind'],
                    'note'        => $c['note'],
                ];
            }
        }

        if (empty($scores)) {
            return ScoringResult::empty();
        }

        // Desempate determinista: mayor score; si empate, mas eventos distintos;
        // si sigue empate, project_id menor.
        $sorted = $scores;
        uksort($sorted, function ($a, $b) use ($scores, $contribsByProject) {
            $diff = ($scores[$b] ?? 0) <=> ($scores[$a] ?? 0);
            if ($diff !== 0) return $diff;
            $ea = count($contribsByProject[$a] ?? []);
            $eb = count($contribsByProject[$b] ?? []);
            $diff = $eb <=> $ea;
            if ($diff !== 0) return $diff;
            return $a <=> $b;
        });
        $winnerId = array_key_first($sorted);
        $topScore = $sorted[$winnerId];

        $confidence = $this->computeConfidence($sorted, $topScore);

        // Evidencia: una fila por evento que contribuyo al dominante.
        // Si el mismo evento aporto multiples veces al dominante, sumamos pesos.
        $evidence = $this->buildEvidence($contribsByProject[$winnerId] ?? []);
        $rulesFired = $this->countRules($contribsByProject[$winnerId] ?? []);

        return new ScoringResult(
            dominantProjectId: $winnerId,
            confidence: $confidence,
            perProjectScores: $sorted,
            evidence: $evidence,
            isIdle: false,
            rulesFired: $rulesFired,
        );
    }

    // ──────────────────────────────────────────────

    /** @param Collection<int,ActivityEvent> $events */
    private function isDominantlyIdle(Collection $events, int $blockMinutes): bool
    {
        // Aproximacion: si hay al menos un evento idle con state=enter y ningun
        // evento posterior de otra fuente, contamos el bloque como idle.
        // Para precision real necesitariamos ts del enter/exit; M3 v1 simplifica.
        $idleEvents = $events->where('source', ActivityEvent::SOURCE_IDLE);
        if ($idleEvents->isEmpty()) {
            return false;
        }

        // Hay al menos un 'enter' y los eventos no-idle son muy escasos.
        $hasEnter = $idleEvents->contains(fn ($e) => data_get($e->metadata, 'state') === 'enter');
        if (! $hasEnter) {
            return false;
        }
        $nonIdle = $events->where('source', '!=', ActivityEvent::SOURCE_IDLE)->count();
        return $nonIdle === 0;
    }

    /**
     * @param array<int,int> $sortedScores  ordenado desc por score
     */
    private function computeConfidence(array $sortedScores, int $topScore): float
    {
        if ($topScore <= 0) {
            return 0.0;
        }
        $values = array_values($sortedScores);
        $second = $values[1] ?? 0;
        return max(0.0, min(1.0, ($topScore - $second) / $topScore));
    }

    /**
     * Una fila de evidencia por evento, agregando weights si un evento aporta
     * varias veces al dominante.
     *
     * @param list<array{event_id:int,weight:int,signal_kind:string,note:string}> $rawContribs
     * @return list<array{event_id:int,weight:int,signal_kind:string,note:string}>
     */
    private function buildEvidence(array $rawContribs): array
    {
        $byEvent = [];
        foreach ($rawContribs as $c) {
            $eid = $c['event_id'];
            if (! isset($byEvent[$eid])) {
                $byEvent[$eid] = $c;
                continue;
            }
            $byEvent[$eid]['weight'] += $c['weight'];
            $byEvent[$eid]['note']   .= ' + ' . $c['note'];
        }
        return array_values($byEvent);
    }

    /**
     * @param list<array{signal_kind:string}> $rawContribs
     * @return array<string,int>
     */
    private function countRules(array $rawContribs): array
    {
        $out = [];
        foreach ($rawContribs as $c) {
            $kind = $c['signal_kind'];
            $out[$kind] = ($out[$kind] ?? 0) + 1;
        }
        return $out;
    }
}
