<?php

namespace App\Services;

use App\Enums\EntryKind;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\ActiveTimer;
use App\Models\ManualEntry;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TimeBlock;
use Carbon\CarbonImmutable;

/**
 * Servicio del Pomodoro. Centraliza:
 *   - configuración persistida (durations, daily goal)
 *   - transición entre fases (focus → break → focus → long_break)
 *   - cálculo de "% foco real" cruzando contra TimeBlocks del tracker pasivo
 *   - métricas para el dashboard (focus de hoy, racha, siguiente tarea)
 *   - matriz hora × día para el heatmap de /reports
 */
class PomodoroService
{
    /** Defaults razonables — Pomodoro clásico + goal de 2h al día. */
    public const DEFAULTS = [
        'pomodoro_focus_min'        => 25,
        'pomodoro_short_break_min'  => 5,
        'pomodoro_long_break_min'   => 15,
        'pomodoro_cycles_until_long'=> 4,
        'pomodoro_daily_goal_min'   => 120,
    ];

    /** Lee la configuración persistida con defaults aplicados. */
    public function currentConfig(): array
    {
        return Setting::many(self::DEFAULTS);
    }

    /** Persiste configuración (sólo claves conocidas, valores saneados). */
    public function saveConfig(array $values): array
    {
        $cfg = $this->currentConfig();
        foreach (self::DEFAULTS as $key => $default) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $clamped = $this->clampSetting($key, (int) $values[$key]);
            Setting::set($key, $clamped);
            $cfg[$key] = $clamped;
        }
        return $cfg;
    }

    /** Mínimos y máximos sensatos para no permitir 0min ni 24h. */
    private function clampSetting(string $key, int $value): int
    {
        return match ($key) {
            'pomodoro_focus_min'         => max(5, min(120, $value)),
            'pomodoro_short_break_min'   => max(1, min(30, $value)),
            'pomodoro_long_break_min'   => max(5, min(60, $value)),
            'pomodoro_cycles_until_long' => max(2, min(10, $value)),
            'pomodoro_daily_goal_min'    => max(15, min(720, $value)),
            default                       => $value,
        };
    }

    /** Duración (segundos) de la fase actual del timer. */
    public function phaseDurationSeconds(ActiveTimer $timer): int
    {
        $cfg = $this->currentConfig();
        return match ($timer->state) {
            ActiveTimer::STATE_SHORT_BREAK => $cfg['pomodoro_short_break_min'] * 60,
            ActiveTimer::STATE_LONG_BREAK  => $cfg['pomodoro_long_break_min'] * 60,
            default                         => $cfg['pomodoro_focus_min'] * 60,
        };
    }

    /**
     * Decide el siguiente estado en función del actual y de cuántos focus
     * llevamos. Se llama al avanzar (manual o automáticamente) una fase.
     */
    public function nextState(ActiveTimer $timer): string
    {
        $cfg = $this->currentConfig();
        if ($timer->isFocus()) {
            // Si tras este focus completamos un múltiplo de N, toca long break.
            $nextCycleCount = $timer->cycle_count + 1;
            return $nextCycleCount % $cfg['pomodoro_cycles_until_long'] === 0
                ? ActiveTimer::STATE_LONG_BREAK
                : ActiveTimer::STATE_SHORT_BREAK;
        }
        // Tras un break volvemos a focus.
        return ActiveTimer::STATE_FOCUS;
    }

    /**
     * % de foco real (0..1) para una entry. Cruza el rango con TimeBlocks:
     *   - si la entry tiene project_id → tramo enfocado = solapamiento con
     *     bloques cuyo dominant_project_id coincide.
     *   - si no, no podemos medirlo → null.
     *
     * Tolera floats y rangos vacíos.
     */
    public function focusedRatio(CarbonImmutable $start, CarbonImmutable $end, ?int $projectId): ?float
    {
        if (! $projectId) {
            return null;
        }
        $totalSeconds = $end->getTimestamp() - $start->getTimestamp();
        if ($totalSeconds <= 0) {
            return null;
        }

        // Bloques que solapan el rango y cuyo proyecto coincide con la tarea.
        $blocks = TimeBlock::query()
            ->where('dominant_project_id', $projectId)
            ->where('starts_at', '<', $end->format('Y-m-d H:i:s'))
            ->where('ends_at',   '>', $start->format('Y-m-d H:i:s'))
            ->get(['starts_at', 'ends_at']);

        $overlap = 0;
        foreach ($blocks as $b) {
            $bStart = max($start->getTimestamp(), $b->starts_at->getTimestamp());
            $bEnd   = min($end->getTimestamp(),   $b->ends_at->getTimestamp());
            if ($bEnd > $bStart) {
                $overlap += $bEnd - $bStart;
            }
        }
        return round($overlap / $totalSeconds, 3);
    }

    /** Minutos de focus de un día concreto (local tz). */
    public function dailyFocusMinutes(?CarbonImmutable $day = null): int
    {
        $tz   = config('tracker.display_timezone', 'UTC');
        $day  = ($day ?? CarbonImmutable::now($tz))->setTimezone($tz)->startOfDay();
        $next = $day->addDay();

        $entries = ManualEntry::query()
            ->where('kind', EntryKind::Focus)
            ->where('ends_at',   '>', $day->setTimezone('UTC')->format('Y-m-d H:i:s'))
            ->where('starts_at', '<', $next->setTimezone('UTC')->format('Y-m-d H:i:s'))
            ->get(['starts_at', 'ends_at']);

        $minutes = 0;
        $rangeStart = $day->setTimezone('UTC');
        $rangeEnd   = $next->setTimezone('UTC');
        foreach ($entries as $e) {
            $s = max($e->starts_at->getTimestamp(), $rangeStart->getTimestamp());
            $f = min($e->ends_at->getTimestamp(),   $rangeEnd->getTimestamp());
            if ($f > $s) {
                $minutes += (int) floor(($f - $s) / 60);
            }
        }
        return $minutes;
    }

    /**
     * Días consecutivos (hasta hoy) en los que se alcanzó el goal. Si hoy
     * aún no se alcanzó, no rompe la racha — sólo cuenta cuando hoy ya
     * pasó o ya se cumplió.
     */
    public function dailyStreak(): int
    {
        $cfg  = $this->currentConfig();
        $goal = (int) $cfg['pomodoro_daily_goal_min'];
        if ($goal <= 0) {
            return 0;
        }
        $tz    = config('tracker.display_timezone', 'UTC');
        $today = CarbonImmutable::now($tz)->startOfDay();

        $streak = 0;
        // Hoy es "soft": no rompe si todavía no se ha alcanzado.
        $todayMin = $this->dailyFocusMinutes($today);
        $cursor   = $todayMin >= $goal ? $today : $today->subDay();

        for ($i = 0; $i < 366; $i++) {
            $min = $this->dailyFocusMinutes($cursor);
            if ($min < $goal) {
                break;
            }
            $streak++;
            $cursor = $cursor->subDay();
        }
        return $streak;
    }

    /**
     * Siguiente tarea sugerida para arrancar pomodoro:
     *   1. Solo estados "actionable" — descarta Blocked, Stand By y Done
     *      (no tiene sentido sugerir pomodoro sobre una tarea bloqueada o en pausa).
     *   2. Status Doing > Todo > Backlog.
     *   3. Prioridad High > Normal > Low.
     *   4. due_date más cercana primero (null al final).
     *   5. Posición ASC.
     */
    public function nextTask(): ?Task
    {
        // Si hay timer en curso, su tarea es "la siguiente" trivial.
        $current = ActiveTimer::with('task')->first();
        if ($current?->task) {
            return $current->task;
        }

        $actionable = collect(TaskStatus::cases())
            ->filter(fn (TaskStatus $s) => $s->isActionable())
            ->map(fn (TaskStatus $s) => $s->value)
            ->all();

        $statusOrder = "CASE status WHEN 'doing' THEN 1 WHEN 'todo' THEN 2 WHEN 'backlog' THEN 3 ELSE 4 END";
        $priorityOrder = "CASE priority WHEN 'high' THEN 1 WHEN 'normal' THEN 2 WHEN 'low' THEN 3 ELSE 4 END";

        return Task::query()
            ->whereIn('status', $actionable)
            ->orderByRaw($statusOrder)
            ->orderByRaw($priorityOrder)
            ->orderByRaw('CASE WHEN due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_date')
            ->orderBy('position')
            ->first();
    }

    /**
     * Matriz hora × weekday (lunes=0 .. domingo=6) con minutos de focus,
     * para el heatmap "tus mejores horas" de /reports. Sólo cuenta entries
     * kind=focus en el periodo dado.
     */
    public function focusHeatmap(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $tz = config('tracker.display_timezone', 'UTC');

        $entries = ManualEntry::query()
            ->where('kind', EntryKind::Focus)
            ->where('ends_at',   '>', $start->setTimezone('UTC')->format('Y-m-d H:i:s'))
            ->where('starts_at', '<', $end->setTimezone('UTC')->format('Y-m-d H:i:s'))
            ->get(['starts_at', 'ends_at']);

        // Inicializo 24×7 a cero. Convención: índice 0 = lunes.
        $matrix = array_fill(0, 7, array_fill(0, 24, 0));

        foreach ($entries as $e) {
            $s = $e->starts_at->copy()->setTimezone($tz);
            $f = $e->ends_at->copy()->setTimezone($tz);
            // Recorto al rango pedido (en UTC, pero los timestamps ya son comparables).
            if ($s->lt($start)) $s = $start->setTimezone($tz);
            if ($f->gt($end))   $f = $end->setTimezone($tz);
            if ($f->lte($s))    continue;

            // Reparto los minutos por hora del día. Bucle hora a hora.
            $cursor = $s->copy();
            while ($cursor->lt($f)) {
                $hourEnd = $cursor->copy()->addHour()->startOfHour();
                $slotEnd = $hourEnd->gt($f) ? $f : $hourEnd;
                $secs    = $slotEnd->getTimestamp() - $cursor->getTimestamp();
                if ($secs > 0) {
                    // isoWeekday: 1..7 (lun..dom) → 0..6.
                    $weekday = $cursor->isoWeekday() - 1;
                    $hour    = (int) $cursor->format('G');
                    $matrix[$weekday][$hour] += (int) floor($secs / 60);
                }
                $cursor = $hourEnd;
            }
        }

        return $matrix;
    }
}
