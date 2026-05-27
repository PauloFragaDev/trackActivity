<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\ActivityEvent;
use App\Models\Note;
use App\Models\Task;
use App\Services\PomodoroService;
use App\Services\SessionBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Página de inicio: la semana actual, un heatmap de actividad, qué ve el
 * tracker ahora mismo, las últimas notas y las tareas en curso.
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly SessionBuilder $sessions,
        private readonly PomodoroService $pomodoro,
    ) {}

    public function index(): View
    {
        $tz     = config('tracker.display_timezone', 'UTC');
        $today  = CarbonImmutable::now($tz)->startOfDay();
        $monday = $today->setISODate($today->isoWeekYear(), $today->isoWeek(), 1)->startOfDay();

        // Minutos trackeados por día — mismo cálculo que la vista de semana.
        $week = [];
        for ($i = 0; $i < 7; $i++) {
            $day      = $monday->addDays($i);
            $sessions = $this->sessions->buildForDay($day);
            $week[] = [
                'date'     => $day,
                'minutes'  => array_sum(array_column($sessions, 'duration_minutes')),
                'is_today' => $day->isSameDay($today),
            ];
        }

        // Último evento del tracker: para "ahora mismo" y para avisar si el
        // daemon parece parado. (Sin eventos no se avisa — instalación nueva.)
        $latestEvent = ActivityEvent::orderByDesc('occurred_at')->first();
        $trackerStaleSince = null;
        if ($latestEvent !== null
            && abs($latestEvent->occurred_at->diffInMinutes(CarbonImmutable::now('UTC'))) >= 20) {
            $trackerStaleSince = $latestEvent->occurred_at;
        }

        // Pomodoro · meta diaria, racha y siguiente tarea sugerida.
        $cfg          = $this->pomodoro->currentConfig();
        $focusToday   = $this->pomodoro->dailyFocusMinutes($today);
        $focusGoal    = (int) $cfg['pomodoro_daily_goal_min'];
        $focusStreak  = $this->pomodoro->dailyStreak();
        $nextTask     = $this->pomodoro->nextTask();

        return view('dashboard.index', [
            'week'              => $week,
            'heatmap'           => $this->heatmap($today),
            'latestEvent'       => $latestEvent,
            'recentNotes'       => Note::orderByDesc('updated_at')->limit(8)->get(),
            'doingTasks'        => Task::with('project')
                ->where('status', TaskStatus::Doing->value)
                ->orderBy('position')
                ->get(),
            'tz'                => $tz,
            'trackerStaleSince' => $trackerStaleSince,
            'focusToday'        => $focusToday,
            'focusGoal'         => $focusGoal,
            'focusStreak'       => $focusStreak,
            'nextTask'          => $nextTask,
        ]);
    }

    /**
     * Rejilla de actividad del último año: semanas (columnas) × 7 días.
     * Los minutos por día se agregan en una sola consulta por tabla.
     *
     * @return list<list<array{date:CarbonImmutable,minutes:int|null}>>
     */
    private function heatmap(CarbonImmutable $today): array
    {
        $start    = $today->subWeeks(52)->startOfWeek(CarbonImmutable::MONDAY);
        $startUtc = $start->setTimezone('UTC')->format('Y-m-d H:i:s');
        $duration = '(julianday(ends_at) - julianday(starts_at)) * 1440';

        $blocks = DB::table('time_blocks')
            ->selectRaw("date(starts_at) as d, SUM($duration) as m")
            ->where('status', '!=', 'idle')
            ->where('starts_at', '>=', $startUtc)
            ->groupBy('d')->pluck('m', 'd');

        $entries = DB::table('manual_entries')
            ->selectRaw("date(starts_at) as d, SUM($duration) as m")
            ->where('starts_at', '>=', $startUtc)
            ->groupBy('d')->pluck('m', 'd');

        $byDay = [];
        foreach ([$blocks, $entries] as $set) {
            foreach ($set as $d => $m) {
                $byDay[$d] = ($byDay[$d] ?? 0) + (int) round((float) $m);
            }
        }

        $weeks  = [];
        $cursor = $start;
        while ($cursor <= $today) {
            $days = [];
            for ($i = 0; $i < 7; $i++) {
                $day = $cursor->addDays($i);
                $days[] = [
                    'date'    => $day,
                    'minutes' => $day->gt($today) ? null : ($byDay[$day->format('Y-m-d')] ?? 0),
                ];
            }
            $weeks[] = $days;
            $cursor  = $cursor->addWeek();
        }

        return $weeks;
    }
}
