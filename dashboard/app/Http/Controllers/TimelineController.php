<?php

namespace App\Http\Controllers;

use App\Models\ActivityEvent;
use App\Models\ManualEntry;
use App\Models\Project;
use App\Services\ModuleVisibility;
use App\Services\SessionBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TimelineController extends Controller
{
    public function __construct(private readonly SessionBuilder $sessions) {}

    // ─────────────────── DIA ───────────────────

    public function today(): View|RedirectResponse
    {
        if (! ModuleVisibility::enabled('tracking')) {
            return redirect()->route('tasks.index');
        }
        return $this->renderDay(CarbonImmutable::now($this->tz()));
    }

    public function day(string $date): View|RedirectResponse
    {
        if (! ModuleVisibility::enabled('tracking')) {
            return redirect()->route('tasks.index');
        }
        $day = CarbonImmutable::parse($date, $this->tz());
        return $this->renderDay($day);
    }

    private function renderDay(CarbonImmutable $day): View
    {
        $tz            = $this->tz();
        $sessions      = $this->sessions->buildForDay($day);
        $manualEntries = $this->manualEntriesForDay($day, $tz);

        // Timeline unificado: sesiones auto + entradas manuales, por hora de inicio.
        $timeline = collect($sessions)
            ->map(fn (array $s) => [
                'type'    => 'session',
                'sort'    => $s['starts_at_local'],
                'session' => $s,
            ])
            ->concat($manualEntries->map(fn (ManualEntry $e) => [
                'type'  => 'manual',
                'sort'  => $e->starts_at->copy()->setTimezone($tz),
                'entry' => $e,
            ]))
            ->sortBy('sort')
            ->values()
            ->all();

        $totals = $this->dayTotals($sessions, $manualEntries);
        $totalMinutes = array_sum($totals->pluck('minutes')->all());

        return view('timeline.day', [
            'day'           => $day,
            'sessions'      => $sessions,
            'manualEntries' => $manualEntries,
            'timeline'      => $timeline,
            'totals'        => $totals,
            'totalMinutes'  => $totalMinutes,
            'tz'            => $tz,
            'projects'      => Project::orderBy('code')->get(),
            'eventCount'    => $this->countRawEvents($day),
            'prevDay'       => $day->subDay()->format('Y-m-d'),
            'nextDay'       => $day->addDay()->format('Y-m-d'),
        ]);
    }

    /** @return Collection<int,ManualEntry> */
    private function manualEntriesForDay(CarbonImmutable $day, string $tz): Collection
    {
        $startLocal = $day->setTimezone($tz)->startOfDay();

        return ManualEntry::query()
            ->with('project')
            ->startingBetween(
                $startLocal->setTimezone('UTC'),
                $startLocal->addDay()->setTimezone('UTC'),
            )
            ->orderBy('starts_at')
            ->get();
    }

    // ─────────────────── SEMANA ───────────────────

    public function thisWeek(): View|RedirectResponse
    {
        if (! ModuleVisibility::enabled('tracking')) {
            return redirect()->route('tasks.index');
        }
        $now = CarbonImmutable::now($this->tz());
        return $this->renderWeek($now->isoWeekYear(), $now->isoWeek());
    }

    public function week(string $week): View|RedirectResponse
    {
        if (! ModuleVisibility::enabled('tracking')) {
            return redirect()->route('tasks.index');
        }
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

    /**
     * Totales por proyecto del día, sumando sesiones auto y entradas manuales.
     *
     * @param  list<array<string,mixed>>      $sessions
     * @param  Collection<int,ManualEntry>    $manualEntries
     * @return Collection<int,array<string,mixed>>
     */
    private function dayTotals(array $sessions, Collection $manualEntries): Collection
    {
        $rows = [];

        $add = function (?Project $project, int $minutes) use (&$rows): void {
            $code = $project?->code ?? '__none__';
            $rows[$code] ??= [
                'project' => $project,
                'code'    => $code === '__none__' ? null : $code,
                'minutes' => 0,
            ];
            $rows[$code]['minutes'] += $minutes;
        };

        foreach ($sessions as $s) {
            $add($s['project'], $s['duration_minutes']);
        }
        foreach ($manualEntries as $entry) {
            $add($entry->project, $entry->durationMinutes());
        }

        return collect($rows)->sortByDesc('minutes')->values();
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
