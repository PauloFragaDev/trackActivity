<?php

namespace App\Http\Controllers;

use App\Enums\EntryKind;
use App\Models\ActiveTimer;
use App\Models\ManualEntry;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cronómetro de tarea (pomodoro). Al arrancar guarda un single-row en
 * active_timers; al parar crea una manual_entry vinculada a la tarea
 * con el tiempo invertido y borra el row.
 */
class TimerController extends Controller
{
    /** Arranca el cronómetro para una tarea, parando antes el anterior si lo había. */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
        ]);

        $task = Task::findOrFail($data['task_id']);

        // Si había otro timer en curso, lo cerramos antes (crea su manual_entry).
        $previous = ActiveTimer::first();
        if ($previous) {
            $this->closeTimer($previous);
        }

        $timer = ActiveTimer::create([
            'task_id'   => $task->id,
            'starts_at' => CarbonImmutable::now('UTC'),
        ]);

        return response()->json([
            'task_id'    => $timer->task_id,
            'task_title' => $task->title,
            'starts_at'  => $timer->starts_at->toIso8601String(),
        ]);
    }

    /** Detiene el cronómetro en curso (si lo hay) y crea la manual_entry. */
    public function stop(): JsonResponse
    {
        $timer = ActiveTimer::first();
        if (! $timer) {
            return response()->json(['running' => false]);
        }

        $entry = $this->closeTimer($timer);

        return response()->json([
            'running'         => false,
            'minutes_logged'  => $entry?->durationMinutes() ?? 0,
            'manual_entry_id' => $entry?->id,
        ]);
    }

    /** Cierra el timer: crea una manual_entry y borra el row. */
    private function closeTimer(ActiveTimer $timer): ?ManualEntry
    {
        $start = CarbonImmutable::parse($timer->starts_at, 'UTC');
        $end   = CarbonImmutable::now('UTC');

        // Si el cronómetro lleva < 1 minuto, descartamos (probable mis-clic).
        if (abs($start->diffInSeconds($end)) < 60) {
            $timer->delete();
            return null;
        }

        $task = $timer->task_id ? Task::find($timer->task_id) : null;

        $entry = ManualEntry::create([
            'starts_at'  => $start,
            'ends_at'    => $end,
            'project_id' => $task?->project_id,
            'task_id'    => $task?->id,
            'kind'       => EntryKind::Focus,
            'title'      => $task ? 'Foco · ' . $task->title : 'Foco',
        ]);

        $timer->delete();

        return $entry;
    }
}
