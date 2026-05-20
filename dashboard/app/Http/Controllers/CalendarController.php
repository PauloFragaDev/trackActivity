<?php

namespace App\Http\Controllers;

use App\Enums\BlockStatus;
use App\Models\TimeBlock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function current(): View
    {
        $now = CarbonImmutable::now($this->tz());
        return $this->renderMonth($now->year, $now->month);
    }

    public function month(string $yearMonth): View
    {
        // formato YYYY-MM
        [$year, $month] = sscanf($yearMonth, '%d-%d');
        return $this->renderMonth((int) $year, (int) $month);
    }

    private function renderMonth(int $year, int $month): View
    {
        $tz = $this->tz();
        $firstOfMonth = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $tz);
        $lastOfMonth  = $firstOfMonth->endOfMonth()->startOfDay();

        // Grid: empezamos en lunes anterior al dia 1, terminamos en domingo posterior al ultimo
        $gridStart = $firstOfMonth->copy()->startOfWeek();        // ISO: lunes
        $gridEnd   = $lastOfMonth->copy()->endOfWeek();           // domingo

        // Query agregada: totales por (dia local, project_id)
        $totalsByDay = $this->aggregateTotalsBetween($gridStart, $gridEnd->addDay());

        $weeks = [];
        $cursor = $gridStart;
        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $date = $cursor;
                $key  = $date->format('Y-m-d');
                $week[] = [
                    'date'      => $date,
                    'in_month'  => $date->month === $month,
                    'projects'  => $totalsByDay[$key] ?? [],
                    'total'     => array_sum(array_map(fn ($p) => $p['minutes'], $totalsByDay[$key] ?? [])),
                ];
                $cursor = $cursor->addDay();
            }
            $weeks[] = $week;
        }

        $monthTotal = 0;
        foreach ($totalsByDay as $key => $rows) {
            $d = CarbonImmutable::parse($key, $tz);
            if ($d->month !== $month) continue;
            $monthTotal += array_sum(array_map(fn ($p) => $p['minutes'], $rows));
        }

        $prev = $firstOfMonth->subMonth();
        $next = $firstOfMonth->addMonth();

        return view('calendar.index', [
            'year'        => $year,
            'month'       => $month,
            'firstDay'    => $firstOfMonth,
            'weeks'       => $weeks,
            'monthTotal'  => $monthTotal,
            'prevMonth'   => sprintf('%04d-%02d', $prev->year, $prev->month),
            'nextMonth'   => sprintf('%04d-%02d', $next->year, $next->month),
            'tz'          => $tz,
        ]);
    }

    /**
     * Devuelve [ 'YYYY-MM-DD' => list<['project'=>Project, 'minutes'=>int]>, ... ]
     * sumando duracion de bloques (ends_at-starts_at) por (dia_local, proyecto).
     */
    private function aggregateTotalsBetween(CarbonImmutable $startLocal, CarbonImmutable $endLocal): array
    {
        $startUtc = $startLocal->setTimezone('UTC')->format('Y-m-d H:i:s');
        $endUtc   = $endLocal->setTimezone('UTC')->format('Y-m-d H:i:s');

        $rows = TimeBlock::query()
            ->with('project')
            ->where('starts_at', '>=', $startUtc)
            ->where('starts_at', '<',  $endUtc)
            ->where('status', '!=', BlockStatus::Idle->value)
            ->whereNotNull('dominant_project_id')
            ->get();

        $out = [];
        $tz = $startLocal->getTimezone()->getName();
        foreach ($rows as $block) {
            $localDay = $block->starts_at->copy()->setTimezone($tz)->format('Y-m-d');
            $minutes  = max(1, (int) $block->starts_at->diffInMinutes($block->ends_at));
            $code     = $block->project?->code ?? '__none__';

            $out[$localDay] ??= [];
            if (! isset($out[$localDay][$code])) {
                $out[$localDay][$code] = ['project' => $block->project, 'minutes' => 0];
            }
            $out[$localDay][$code]['minutes'] += $minutes;
        }

        // Ordenar y reindexar
        foreach ($out as $day => $byCode) {
            uasort($byCode, fn ($a, $b) => $b['minutes'] <=> $a['minutes']);
            $out[$day] = array_values($byCode);
        }
        return $out;
    }

    private function tz(): string
    {
        return config('tracker.display_timezone', 'UTC');
    }
}
