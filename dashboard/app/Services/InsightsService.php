<?php

namespace App\Services;

use App\Enums\BlockStatus;
use App\Models\TimeBlock;
use App\Services\Insights\NarrativeComposer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Calcula los insights de actividad (en vivo, sin persistir) a partir de
 * time_blocks + manual_entries. Reusa ReportsService para el reparto por
 * proyecto (que ya combina ambas fuentes) y deriva las métricas de foco de
 * los bloques ordenados.
 *
 * Definiciones v1:
 *   - cambios de contexto: transiciones de proyecto entre bloques NO-idle
 *     consecutivos (los idle no rompen ni cuentan: se comprimen).
 *   - racha de foco: tramo contiguo más largo (minutos) del mismo proyecto.
 *   - deep-work: minutos en tramos contiguos de ≥ 25 min / minutos no-idle.
 */
class InsightsService
{
    private const DEEP_WORK_MIN = 25;

    public function __construct(private readonly ReportsService $reports) {}

    /** Insights del día (rango local [día, día+1)). */
    public function forDay(CarbonImmutable $day): array
    {
        $start = $day->startOfDay();
        return $this->build('day', $start, $start->addDay());
    }

    /** Insights de la semana (rango local [lunes, lunes+7)). */
    public function forWeek(CarbonImmutable $monday): array
    {
        $start = $monday->startOfDay();
        return $this->build('week', $start, $start->addDays(7));
    }

    /**
     * Minutos por proyecto por semana en las últimas $weeks semanas ISO.
     *
     * @return array{labels:list<string>, series:list<array{name:string,color:string,data:list<int>}>}
     */
    public function projectTrend(int $weeks = 8): array
    {
        $tz         = (string) config('tracker.display_timezone', 'UTC');
        $thisMonday = CarbonImmutable::now($tz)->startOfWeek(CarbonImmutable::MONDAY);

        $labels = [];
        $weekly = [];   // índice semana => [pid|0 => minutes]
        $meta   = [];   // pid|0 => ['name'=>, 'color'=>]

        for ($w = $weeks - 1; $w >= 0; $w--) {
            $monday = $thisMonday->subWeeks($w);
            $labels[] = $monday->format('d/m');
            $byProject = $this->reports->byProject($monday, $monday->addDays(7));

            $row = [];
            foreach ($byProject as $p) {
                $key = $p['project_id'] ?? 0;
                $row[$key] = (int) $p['minutes'];
                $meta[$key] ??= ['name' => $p['project_name'], 'color' => $p['color']];
            }
            $weekly[] = $row;
        }

        $series = [];
        foreach ($meta as $key => $info) {
            $data = [];
            foreach ($weekly as $row) {
                $data[] = $row[$key] ?? 0;
            }
            $series[] = ['name' => $info['name'], 'color' => $info['color'], 'data' => $data];
        }

        return ['labels' => $labels, 'series' => $series];
    }

    private function build(string $period, CarbonImmutable $startLocal, CarbonImmutable $endLocal): array
    {
        $byProject = $this->reports->byProject($startLocal, $endLocal);
        $focus     = $this->focusMetrics($this->blocksIn($startLocal, $endLocal));

        $metrics = [
            'period'                 => $period,
            'by_project'             => $byProject,
            'active_minutes'         => (int) array_sum(array_column($byProject, 'minutes')),
            'idle_minutes'           => $focus['idle_minutes'],
            'context_switches'       => $focus['context_switches'],
            'longest_focus_minutes'  => $focus['longest_focus_minutes'],
            'deep_work_minutes'      => $focus['deep_work_minutes'],
            'deep_work_pct'          => $focus['deep_work_pct'],
        ];

        $metrics['narrative'] = NarrativeComposer::compose($period, $metrics);

        return $metrics;
    }

    private function blocksIn(CarbonImmutable $startLocal, CarbonImmutable $endLocal): Collection
    {
        return TimeBlock::query()
            ->where('starts_at', '>=', $startLocal->setTimezone('UTC')->format('Y-m-d H:i:s'))
            ->where('starts_at', '<',  $endLocal->setTimezone('UTC')->format('Y-m-d H:i:s'))
            ->orderBy('starts_at')
            ->get(['starts_at', 'ends_at', 'dominant_project_id', 'status']);
    }

    /**
     * @param  Collection<int,TimeBlock>  $blocks
     * @return array{idle_minutes:int,context_switches:int,longest_focus_minutes:int,deep_work_minutes:int,deep_work_pct:int}
     */
    private function focusMetrics(Collection $blocks): array
    {
        $idle = 0;
        $seq  = [];   // bloques no-idle, en orden: ['pid'=>?int,'min'=>int]

        foreach ($blocks as $b) {
            $min = (int) round($b->starts_at->diffInMinutes($b->ends_at));
            if ($b->status === BlockStatus::Idle) {
                $idle += $min;
                continue;
            }
            $seq[] = ['pid' => $b->dominant_project_id, 'min' => $min];
        }

        // Cambios de contexto: transiciones de proyecto en la secuencia no-idle.
        $switches = 0;
        for ($i = 1; $i < count($seq); $i++) {
            if ($seq[$i]['pid'] !== $seq[$i - 1]['pid']) {
                $switches++;
            }
        }

        // Tramos contiguos del mismo proyecto.
        $runs    = [];
        $curPid  = null;
        $curMin  = 0;
        $started = false;
        foreach ($seq as $s) {
            if ($started && $s['pid'] === $curPid) {
                $curMin += $s['min'];
            } else {
                if ($started) {
                    $runs[] = $curMin;
                }
                $curPid  = $s['pid'];
                $curMin  = $s['min'];
                $started = true;
            }
        }
        if ($started) {
            $runs[] = $curMin;
        }

        $longest = 0;
        $deep    = 0;
        $total   = 0;
        foreach ($runs as $runMin) {
            $total  += $runMin;
            $longest = max($longest, $runMin);
            if ($runMin >= self::DEEP_WORK_MIN) {
                $deep += $runMin;
            }
        }

        return [
            'idle_minutes'          => $idle,
            'context_switches'      => $switches,
            'longest_focus_minutes' => $longest,
            'deep_work_minutes'     => $deep,
            'deep_work_pct'         => $total > 0 ? (int) round(100 * $deep / $total) : 0,
        ];
    }
}
