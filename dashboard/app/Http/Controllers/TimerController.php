<?php

namespace App\Http\Controllers;

use App\Enums\EntryKind;
use App\Models\ActiveTimer;
use App\Models\ManualEntry;
use App\Models\Task;
use App\Services\PomodoroService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cronómetro/Pomodoro de tarea.
 *
 *   start    → arranca un nuevo focus en una tarea (cierra el anterior si lo había)
 *   advance  → cierra la fase actual y pasa a la siguiente (focus → break → focus…)
 *              · si la fase cerrada era un focus, materializa una manual_entry
 *   pause/resume → congela y reanuda el contador de la fase actual
 *   stop     → cierra todo (con metadata opcional: mood/progress/notes) y borra el row
 *   next     → devuelve la siguiente tarea sugerida (no toca BBDD)
 */
class TimerController extends Controller
{
    public function __construct(private readonly PomodoroService $pomodoro) {}

    /** Arranca el cronómetro para una tarea, cerrando antes el anterior si lo había. */
    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'integer', 'exists:tasks,id'],
        ]);

        $task = Task::findOrFail($data['task_id']);

        // Si había otro timer en curso, lo cerramos antes (con su manual_entry).
        $previous = ActiveTimer::first();
        if ($previous) {
            $this->closeTimer($previous);
        }

        $now = CarbonImmutable::now('UTC');
        $timer = ActiveTimer::create([
            'task_id'               => $task->id,
            'state'                 => ActiveTimer::STATE_FOCUS,
            'cycle_count'           => 0,
            'starts_at'             => $now,
            'phase_started_at'      => $now,
            'paused_at'             => null,
            'paused_offset_seconds' => 0,
        ]);

        return response()->json($this->timerPayload($timer));
    }

    /**
     * Cierra la fase actual y avanza a la siguiente. Si la cerrada era un
     * focus, crea su manual_entry. Si era un break, simplemente pasa.
     */
    public function advance(Request $request): JsonResponse
    {
        $timer = ActiveTimer::first();
        if (! $timer) {
            return response()->json(['running' => false]);
        }

        $entry = null;
        if ($timer->isFocus()) {
            $entry = $this->materializeFocusEntry($timer, $request->all());
        }

        // Calculo siguiente estado ANTES de mutar (necesita cycle_count actual).
        $next = $this->pomodoro->nextState($timer);
        $now  = CarbonImmutable::now('UTC');

        $timer->fill([
            'state'                 => $next,
            'cycle_count'           => $timer->isFocus() ? $timer->cycle_count + 1 : $timer->cycle_count,
            'phase_started_at'      => $now,
            'paused_at'             => null,
            'paused_offset_seconds' => 0,
        ])->save();

        return response()->json($this->timerPayload($timer->fresh('task'), [
            'advanced_from_focus' => $entry !== null,
            'manual_entry_id'     => $entry?->id,
            'minutes_logged'      => $entry?->durationMinutes() ?? 0,
        ]));
    }

    public function pause(): JsonResponse
    {
        $timer = ActiveTimer::first();
        if (! $timer || $timer->isPaused()) {
            return response()->json(['running' => $timer !== null, 'paused' => true]);
        }
        $timer->update(['paused_at' => CarbonImmutable::now('UTC')]);
        return response()->json($this->timerPayload($timer->fresh('task')));
    }

    public function resume(): JsonResponse
    {
        $timer = ActiveTimer::first();
        if (! $timer || ! $timer->isPaused()) {
            return response()->json(['running' => $timer !== null, 'paused' => false]);
        }
        $pausedSec = max(0, CarbonImmutable::now('UTC')->getTimestamp() - $timer->paused_at->getTimestamp());
        $timer->update([
            'paused_at'             => null,
            'paused_offset_seconds' => $timer->paused_offset_seconds + $pausedSec,
        ]);
        return response()->json($this->timerPayload($timer->fresh('task')));
    }

    /**
     * Para el cronómetro. Si estaba en focus, crea manual_entry (con metadata
     * del modal de cierre si llega). Si estaba en break, simplemente borra.
     */
    public function stop(Request $request): JsonResponse
    {
        $timer = ActiveTimer::first();
        if (! $timer) {
            return response()->json(['running' => false]);
        }

        $entry = null;
        if ($timer->isFocus()) {
            $entry = $this->materializeFocusEntry($timer, $request->all());
        }
        $timer->delete();

        return response()->json([
            'running'         => false,
            'minutes_logged'  => $entry?->durationMinutes() ?? 0,
            'manual_entry_id' => $entry?->id,
            'focused_ratio'   => $entry?->focused_ratio,
        ]);
    }

    /** Devuelve la siguiente tarea sugerida (sin tocar BBDD). */
    public function next(): JsonResponse
    {
        $task = $this->pomodoro->nextTask();
        if (! $task) {
            return response()->json(['task' => null]);
        }
        return response()->json([
            'task' => [
                'id'    => $task->id,
                'title' => $task->title,
            ],
        ]);
    }

    /**
     * Cierre interno usado por start() cuando hay otro timer en curso.
     * Borra el row siempre; crea manual_entry si era focus y > 60s.
     */
    private function closeTimer(ActiveTimer $timer): ?ManualEntry
    {
        $entry = null;
        if ($timer->isFocus()) {
            $entry = $this->materializeFocusEntry($timer, []);
        }
        $timer->delete();
        return $entry;
    }

    /**
     * Crea la manual_entry de un focus. Aplica metadata si llega del modal
     * de cierre (mood/progress/notes). Descarta si < 60 s de trabajo neto.
     *
     * El trabajo neto descuenta pausas: usa phase_started_at + paused_offset.
     */
    private function materializeFocusEntry(ActiveTimer $timer, array $meta): ?ManualEntry
    {
        $startPhase = $timer->phase_started_at
            ? CarbonImmutable::parse($timer->phase_started_at, 'UTC')
            : CarbonImmutable::parse($timer->starts_at, 'UTC');
        $end = CarbonImmutable::now('UTC');

        // Si quedó pausado, descontamos también ese tramo final.
        $pausedExtra = $timer->isPaused()
            ? max(0, $end->getTimestamp() - $timer->paused_at->getTimestamp())
            : 0;
        $netSeconds = $end->getTimestamp() - $startPhase->getTimestamp()
            - $timer->paused_offset_seconds - $pausedExtra;

        if ($netSeconds < 60) {
            return null;
        }

        $task = $timer->task_id ? Task::find($timer->task_id) : null;
        // La entry refleja el rango "real" (con pausas dentro). El usuario
        // pidió foco entre X e Y; las pausas se descuentan en focused_ratio.
        $entryStart = $startPhase;
        $entryEnd   = $timer->isPaused() ? CarbonImmutable::parse($timer->paused_at, 'UTC') : $end;

        $ratio = $this->pomodoro->focusedRatio($entryStart, $entryEnd, $task?->project_id);

        $mood     = isset($meta['mood']) ? max(1, min(5, (int) $meta['mood'])) : null;
        $progress = in_array($meta['progress'] ?? null, ['yes', 'partial', 'no'], true)
            ? $meta['progress'] : null;
        $notes    = is_string($meta['notes'] ?? null) && trim($meta['notes']) !== ''
            ? mb_substr(trim($meta['notes']), 0, 2000) : null;

        return ManualEntry::create([
            'starts_at'     => $entryStart,
            'ends_at'       => $entryEnd,
            'project_id'    => $task?->project_id,
            'task_id'       => $task?->id,
            'kind'          => EntryKind::Focus,
            'title'         => $task ? 'Foco · ' . $task->title : 'Foco',
            'notes'         => $notes,
            'mood'          => $mood,
            'progress'      => $progress,
            'focused_ratio' => $ratio,
        ]);
    }

    /** Payload común que devuelve el estado del timer al cliente. */
    private function timerPayload(?ActiveTimer $timer, array $extra = []): array
    {
        if (! $timer) {
            return array_merge(['running' => false], $extra);
        }
        $cfg = $this->pomodoro->currentConfig();
        return array_merge([
            'running'                => true,
            'task_id'                => $timer->task_id,
            'task_title'             => $timer->task?->title,
            'state'                  => $timer->state,
            'cycle_count'            => $timer->cycle_count,
            'phase_started_at'       => $timer->phase_started_at?->toIso8601String(),
            'paused_at'              => $timer->paused_at?->toIso8601String(),
            'paused_offset_seconds'  => $timer->paused_offset_seconds,
            'phase_duration_seconds' => $this->pomodoro->phaseDurationSeconds($timer),
            'config'                 => $cfg,
        ], $extra);
    }
}
