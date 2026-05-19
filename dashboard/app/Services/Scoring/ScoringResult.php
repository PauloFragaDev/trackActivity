<?php

namespace App\Services\Scoring;

/**
 * Resultado de puntuar un conjunto de eventos para un bloque temporal.
 *
 * Inmutable. Lo produce `Scorer` y lo consume `Aggregator` para persistir
 * `time_blocks`, `time_block_evidence` y `scoring_snapshot`.
 */
final class ScoringResult
{
    /**
     * @param array<int, int>                                                    $perProjectScores  project_id => score
     * @param list<array{event_id:int,weight:int,signal_kind:string,note:string}> $evidence          contribuciones al proyecto dominante
     * @param array<string, int>                                                  $rulesFired        signal_kind => count, sobre el dominante
     */
    public function __construct(
        public readonly ?int $dominantProjectId,
        public readonly float $confidence,
        public readonly array $perProjectScores,
        public readonly array $evidence,
        public readonly bool $isIdle,
        public readonly array $rulesFired,
    ) {}

    public static function empty(): self
    {
        return new self(null, 0.0, [], [], false, []);
    }

    public static function idle(): self
    {
        return new self(null, 0.0, [], [], true, []);
    }

    public function totalScore(): int
    {
        return array_sum($this->perProjectScores);
    }

    /**
     * Devuelve la lista top-N de proyectos ordenados por score desc.
     * Cada entrada: ['project_id'=>int, 'score'=>int].
     */
    public function topN(int $n = 5): array
    {
        $scores = $this->perProjectScores;
        arsort($scores);
        $out = [];
        foreach ($scores as $projectId => $score) {
            $out[] = ['project_id' => $projectId, 'score' => $score];
            if (count($out) >= $n) break;
        }
        return $out;
    }

    public function snapshot(): array
    {
        $top = $this->topN(5);
        return [
            'winner'      => $this->dominantProjectId === null ? null : [
                'project_id' => $this->dominantProjectId,
                'score'      => $this->perProjectScores[$this->dominantProjectId] ?? 0,
            ],
            'runners_up'  => array_slice($top, 1),
            'rules_fired' => $this->rulesFired,
            'computed_at' => now('UTC')->toIso8601String(),
        ];
    }
}
