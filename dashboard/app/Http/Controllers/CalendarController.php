<?php

namespace App\Http\Controllers;

use App\Enums\BlockStatus;
use App\Models\ManualEntry;
use App\Models\Project;
use App\Models\TimeBlock;
use Carbon\CarbonImmutable;
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

        // Día por defecto del formulario de alta: hoy si cae en el mes mostrado.
        $today = CarbonImmutable::now($tz);
        $formDate = ($today->year === $year && $today->month === $month)
            ? $today->format('Y-m-d')
            : $firstOfMonth->format('Y-m-d');

        return view('calendar.index', [
            'year'        => $year,
            'month'       => $month,
            'firstDay'    => $firstOfMonth,
            'weeks'       => $weeks,
            'monthTotal'  => $monthTotal,
            'prevMonth'   => sprintf('%04d-%02d', $prev->year, $prev->month),
            'nextMonth'   => sprintf('%04d-%02d', $next->year, $next->month),
            'tz'          => $tz,
            'projects'    => Project::orderBy('code')->get(),
            'formDate'    => $formDate,
        ]);
    }

    /**
     * Devuelve [ 'YYYY-MM-DD' => list<['project'=>Project, 'minutes'=>int]>, ... ]
     * sumando, por (dia_local, proyecto), la duración de los time_blocks auto
     * y de las entradas manuales.
     */
    private function aggregateTotalsBetween(CarbonImmutable $startLocal, CarbonImmutable $endLocal): array
    {
        $startUtc = $startLocal->setTimezone('UTC')->format('Y-m-d H:i:s');
        $endUtc   = $endLocal->setTimezone('UTC')->format('Y-m-d H:i:s');
        $tz       = $startLocal->getTimezone()->getName();

        $out = [];
        $accumulate = function (string $localDay, ?Project $project, int $minutes) use (&$out): void {
            $code = $project?->code ?? '__none__';
            $out[$localDay] ??= [];
            $out[$localDay][$code] ??= ['project' => $project, 'minutes' => 0];
            $out[$localDay][$code]['minutes'] += $minutes;
        };

        $blocks = TimeBlock::query()
            ->with('project')
            ->where('starts_at', '>=', $startUtc)
            ->where('starts_at', '<',  $endUtc)
            ->where('status', '!=', BlockStatus::Idle->value)
            ->whereNotNull('dominant_project_id')
            ->get();
        foreach ($blocks as $block) {
            $accumulate(
                $block->starts_at->copy()->setTimezone($tz)->format('Y-m-d'),
                $block->project,
                max(1, (int) $block->starts_at->diffInMinutes($block->ends_at)),
            );
        }

        $entries = ManualEntry::query()
            ->with('project')
            ->where('starts_at', '>=', $startUtc)
            ->where('starts_at', '<',  $endUtc)
            ->get();
        foreach ($entries as $entry) {
            $accumulate(
                $entry->starts_at->copy()->setTimezone($tz)->format('Y-m-d'),
                $entry->project,
                $entry->durationMinutes(),
            );
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
