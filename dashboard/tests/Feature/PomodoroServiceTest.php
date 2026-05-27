<?php

namespace Tests\Feature;

use App\Enums\EntryKind;
use App\Models\ManualEntry;
use App\Models\Setting;
use App\Models\Task;
use App\Services\PomodoroService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PomodoroServiceTest extends TestCase
{
    use RefreshDatabase;

    private function svc(): PomodoroService
    {
        return app(PomodoroService::class);
    }

    public function test_defaults_are_applied_when_no_settings_present(): void
    {
        $cfg = $this->svc()->currentConfig();
        $this->assertSame(25, $cfg['pomodoro_focus_min']);
        $this->assertSame(120, $cfg['pomodoro_daily_goal_min']);
    }

    public function test_save_config_clamps_out_of_range_values(): void
    {
        $cfg = $this->svc()->saveConfig([
            'pomodoro_focus_min'      => 999,    // se recorta a 120
            'pomodoro_daily_goal_min' => 1,      // sube a 15
        ]);
        $this->assertSame(120, $cfg['pomodoro_focus_min']);
        $this->assertSame(15, $cfg['pomodoro_daily_goal_min']);
        $this->assertSame(120, Setting::get('pomodoro_focus_min'));
    }

    public function test_daily_focus_minutes_only_counts_focus_kind_in_day(): void
    {
        $tz   = config('tracker.display_timezone', 'UTC');
        $today = CarbonImmutable::now($tz)->setTime(10, 0)->setTimezone('UTC');

        ManualEntry::create([
            'starts_at' => $today,
            'ends_at'   => $today->addMinutes(45),
            'kind'      => EntryKind::Focus,
            'title'     => 'Foco',
        ]);
        // Meeting no cuenta.
        ManualEntry::create([
            'starts_at' => $today->addHour(),
            'ends_at'   => $today->addHour()->addMinutes(30),
            'kind'      => EntryKind::Meeting,
            'title'     => 'Daily',
        ]);
        // Focus de ayer tampoco.
        ManualEntry::create([
            'starts_at' => $today->subDay(),
            'ends_at'   => $today->subDay()->addMinutes(60),
            'kind'      => EntryKind::Focus,
            'title'     => 'Foco viejo',
        ]);

        $this->assertSame(45, $this->svc()->dailyFocusMinutes());
    }

    public function test_daily_streak_counts_consecutive_days_meeting_goal(): void
    {
        Setting::set('pomodoro_daily_goal_min', 30);
        $tz = config('tracker.display_timezone', 'UTC');

        // 3 días consecutivos cumpliendo (ayer, anteayer, hace 3) — hoy todavía no.
        foreach ([1, 2, 3] as $back) {
            $day = CarbonImmutable::now($tz)->subDays($back)->setTime(10, 0)->setTimezone('UTC');
            ManualEntry::create([
                'starts_at' => $day,
                'ends_at'   => $day->addMinutes(45),
                'kind'      => EntryKind::Focus,
                'title'     => 'Foco',
            ]);
        }
        $this->assertSame(3, $this->svc()->dailyStreak());
    }

    public function test_next_task_orders_by_status_then_priority_then_due(): void
    {
        // Backlog low — debería ser la última.
        Task::create(['title' => 'Z', 'status' => 'backlog', 'priority' => 'low']);
        // Todo high con due más lejana.
        Task::create(['title' => 'B', 'status' => 'todo', 'priority' => 'high', 'due_date' => '2030-01-01']);
        // Todo high con due próxima — gana entre los Todo.
        $todoNear = Task::create(['title' => 'A', 'status' => 'todo', 'priority' => 'high', 'due_date' => '2026-06-01']);
        // Doing normal — gana sobre todos los Todo por estado.
        $doing = Task::create(['title' => 'D', 'status' => 'doing', 'priority' => 'normal']);

        $next = $this->svc()->nextTask();
        $this->assertSame($doing->id, $next->id);

        $doing->update(['status' => 'done', 'completed_at' => now()]);
        $next = $this->svc()->nextTask();
        $this->assertSame($todoNear->id, $next->id);
    }

    public function test_focus_heatmap_distributes_minutes_by_hour_and_weekday(): void
    {
        $tz   = config('tracker.display_timezone', 'UTC');
        // Miércoles 27 mayo 2026 — 10:00–10:30 local.
        $start = CarbonImmutable::create(2026, 5, 27, 10, 0, 0, $tz);
        ManualEntry::create([
            'starts_at' => $start->setTimezone('UTC'),
            'ends_at'   => $start->addMinutes(30)->setTimezone('UTC'),
            'kind'      => EntryKind::Focus,
            'title'     => 'F',
        ]);

        $matrix = $this->svc()->focusHeatmap($start->subDay(), $start->addDay());
        // Miércoles = isoWeekday 3 → index 2. Hora 10.
        $this->assertSame(30, $matrix[2][10]);
        // Las demás celdas a 0.
        $this->assertSame(0, $matrix[2][11]);
        $this->assertSame(0, $matrix[1][10]);
    }
}
