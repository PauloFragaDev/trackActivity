<?php

namespace App\Http\Controllers;

use App\Models\Note;
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

        return view('dashboard.index', [
            'week'        => $week,
            'recentNotes' => Note::orderByDesc('updated_at')->limit(8)->get(),
            'tz'          => $tz,
        ]);
    }
}
