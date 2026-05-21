<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\ActivityEvent;
use App\Models\Note;
use App\Models\Task;
use App\Services\SessionBuilder;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * Página de inicio: la semana actual de un vistazo, las últimas notas
 * editadas y (a futuro) las tareas en curso del módulo Kanban.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly SessionBuilder $sessions) {}

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

        // Salud del tracker: si el último evento es muy viejo, probablemente
        // el daemon esté parado. (Si no hay ningún evento, no se avisa: puede
        // ser una instalación nueva donde el tracker aún no ha corrido.)
        $lastEvent = ActivityEvent::max('occurred_at');
        $trackerStaleSince = null;
        if ($lastEvent !== null) {
            $last = CarbonImmutable::parse($lastEvent, 'UTC');
            if (abs($last->diffInMinutes(CarbonImmutable::now('UTC'))) >= 20) {
                $trackerStaleSince = $last;
            }
        }

        return view('dashboard.index', [
            'week'              => $week,
            'recentNotes'       => Note::orderByDesc('updated_at')->limit(8)->get(),
            'doingTasks'        => Task::with('project')
                ->where('status', TaskStatus::Doing->value)
                ->orderBy('position')
                ->get(),
            'tz'                => $tz,
            'trackerStaleSince' => $trackerStaleSince,
        ]);
    }
}
