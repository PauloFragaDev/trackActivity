<?php

namespace App\Http\Controllers;

use App\Models\ActivityEvent;
use App\Models\Project;
use App\Services\SessionBuilder;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class TimelineController extends Controller
{
    public function __construct(private readonly SessionBuilder $sessions) {}

    // ─────────────────── DIA ───────────────────

    public function today(): View
    {
        return $this->renderDay(CarbonImmutable::now($this->tz()));
    }

    public function day(string $date): View
    {
        $day = CarbonImmutable::parse($date, $this->tz());
        return $this->renderDay($day);
    }

    private function renderDay(CarbonImmutable $day): View
    {
        $sessions = $this->sessions->buildForDay($day);

        $totals = $this->totalsByProject($sessions);
        $totalMinutes = array_sum($totals->pluck('minutes')->all());

        return view('timeline.day', [
            'day'           => $day,
            'sessions'      => $sessions,
            'totals'        => $totals,
            'totalMinutes'  => $totalMinutes,
            'tz'            => $this->tz(),
            'projects'      => Project::orderBy('code')->get(),
            'eventCount'    => $this->countRawEvents($day),
            'prevDay'       => $day->subDay()->format('Y-m-d'),
            'nextDay'       => $day->addDay()->format('Y-m-d'),
        ]);
    }

    // ─────────────────── SEMANA ───────────────────

    public function thisWeek(): View
    {
        $now = CarbonImmutable::now($this->tz());
        return $this->renderWeek($now->isoWeekYear(), $now->isoWeek());
    }

    public function week(string $week): View
    {
        // formato "YYYY-Www" e.g. "2026-W21"
        [$year, $w] = sscanf($week, '%d-W%d');
        return $this->renderWeek((int) $year, (int) $w);
    }

    private function renderWeek(int $isoYear, int $isoWeek): View
    {
        $monday = CarbonImmutable::now($this->tz())
            ->setISODate($isoYear, $isoWeek, 1)
            ->startOfDay();

        $days = [];
        $weekTotals = collect();
        $totalMinutes = 0;

        for ($i = 0; $i < 7; $i++) {
            $day = $monday->addDays($i);
            $sessions = $this->sessions->buildForDay($day);
            $dayMinutes = array_sum(array_column($sessions, 'duration_minutes'));
            $totalMinutes += $dayMinutes;

            $byProject = collect($sessions)
                ->groupBy(fn ($s) => $s['project']?->code ?? '__none__')
                ->map(fn ($g) => [
                    'project' => $g->first()['project'],
                    'minutes' => $g->sum('duration_minutes'),
                ])
                ->sortByDesc('minutes')
                ->values();

            foreach ($byProject as $row) {
                $key  = $row['project']?->code ?? '__none__';
                $prev = $weekTotals->get($key)['minutes'] ?? 0;
                $weekTotals->put($key, [
                    'project' => $row['project'],
                    'minutes' => $prev + $row['minutes'],
                ]);
            }

            $days[] = [
                'date'        => $day,
                'sessions'    => $sessions,
                'minutes'     => $dayMinutes,
                'by_project'  => $byProject,
            ];
        }

        $totals = $weekTotals->values()->sortByDesc('minutes')->values();
        $prev = $monday->subWeek();
        $next = $monday->addWeek();

        return view('timeline.week', [
            'year'         => $isoYear,
            'week'         => $isoWeek,
            'monday'       => $monday,
            'sunday'       => $monday->addDays(6),
            'days'         => $days,
            'totals'       => $totals,
            'totalMinutes' => $totalMinutes,
            'tz'           => $this->tz(),
            'prevWeek'     => $prev->isoFormat('GGGG') . '-W' . str_pad((string) $prev->isoWeek(), 2, '0', STR_PAD_LEFT),
            'nextWeek'     => $next->isoFormat('GGGG') . '-W' . str_pad((string) $next->isoWeek(), 2, '0', STR_PAD_LEFT),
        ]);
    }

    // ─────────────────── HELPERS ───────────────────

    private function totalsByProject(array $sessions): \Illuminate\Support\Collection
    {
        return collect($sessions)
            ->groupBy(fn ($s) => $s['project']?->code ?? '__none__')
            ->map(fn ($group, $key) => [
                'project' => $group->first()['project'],
                'code'    => $key === '__none__' ? null : $key,
                'minutes' => $group->sum('duration_minutes'),
            ])
            ->sortByDesc('minutes')
            ->values();
    }

    private function countRawEvents(CarbonImmutable $day): int
    {
        $tz    = $this->tz();
        $start = $day->setTimezone($tz)->startOfDay()->setTimezone('UTC');
        $end   = $start->copy()->addDay();
        return ActivityEvent::query()
            ->where('occurred_at', '>=', $start->format('Y-m-d H:i:s'))
            ->where('occurred_at', '<',  $end->format('Y-m-d H:i:s'))
            ->count();
    }

    private function tz(): string
    {
        return config('tracker.display_timezone', 'UTC');
    }
}
