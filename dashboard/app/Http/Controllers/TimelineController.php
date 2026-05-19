<?php

namespace App\Http\Controllers;

use App\Models\ActivityEvent;
use App\Models\Project;
use App\Services\SessionBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimelineController extends Controller
{
    public function __construct(private readonly SessionBuilder $sessions) {}

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
