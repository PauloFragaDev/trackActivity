<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\ActiveTimer;
use App\Models\ManualEntry;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TimeBlock;
use App\Services\PomodoroService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimerTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_creates_an_active_timer(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);

        $this->post('/timer/start', ['task_id' => $task->id])
            ->assertOk()
            ->assertJson(['task_id' => $task->id, 'task_title' => 'T']);

        $this->assertSame(1, ActiveTimer::count());
        $timer = ActiveTimer::firstOrFail();
        $this->assertSame($task->id, $timer->task_id);
        $this->assertSame(ActiveTimer::STATE_FOCUS, $timer->state);
        $this->assertSame(0, $timer->cycle_count);
        $this->assertNotNull($timer->phase_started_at);
    }

    public function test_start_requires_task_id_to_exist(): void
    {
        $this->post('/timer/start', ['task_id' => 999_999])
            ->assertSessionHasErrors('task_id');
    }

    public function test_starting_a_new_timer_closes_the_previous(): void
    {
        $project = Project::create(['code' => 'P', 'name' => 'P', 'color' => '#000']);
        $taskA   = Task::create(['title' => 'A', 'status' => 'todo', 'project_id' => $project->id]);
        $taskB   = Task::create(['title' => 'B', 'status' => 'todo']);

        $now = CarbonImmutable::now('UTC');
        ActiveTimer::create([
            'task_id'          => $taskA->id,
            'state'            => ActiveTimer::STATE_FOCUS,
            'cycle_count'      => 0,
            'starts_at'        => $now->subMinutes(5),
            'phase_started_at' => $now->subMinutes(5),
        ]);

        $this->post('/timer/start', ['task_id' => $taskB->id])->assertOk();

        $this->assertSame(1, ActiveTimer::count());
        $this->assertSame($taskB->id, ActiveTimer::firstOrFail()->task_id);

        $entry = ManualEntry::firstOrFail();
        $this->assertSame($taskA->id, $entry->task_id);
        $this->assertSame($project->id, $entry->project_id);
        $this->assertGreaterThanOrEqual(5, $entry->durationMinutes());
    }

    public function test_stop_closes_active_timer_and_creates_manual_entry_with_metadata(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        $now  = CarbonImmutable::now('UTC');
        ActiveTimer::create([
            'task_id'          => $task->id,
            'state'            => ActiveTimer::STATE_FOCUS,
            'cycle_count'      => 0,
            'starts_at'        => $now->subMinutes(25),
            'phase_started_at' => $now->subMinutes(25),
        ]);

        $this->post('/timer/stop', [
            'mood'     => 4,
            'progress' => 'yes',
            'notes'    => 'Acabé la migración',
        ])
            ->assertOk()
            ->assertJsonStructure(['running', 'minutes_logged', 'manual_entry_id']);

        $this->assertSame(0, ActiveTimer::count());
        $entry = ManualEntry::firstOrFail();
        $this->assertSame($task->id, $entry->task_id);
        $this->assertGreaterThanOrEqual(25, $entry->durationMinutes());
        $this->assertSame(4, $entry->mood);
        $this->assertSame('yes', $entry->progress);
        $this->assertSame('Acabé la migración', $entry->notes);
    }

    public function test_stop_without_active_timer_is_a_noop(): void
    {
        $this->post('/timer/stop')->assertOk()->assertJson(['running' => false]);
        $this->assertSame(0, ManualEntry::count());
    }

    public function test_stop_under_one_minute_does_not_create_entry(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        $now  = CarbonImmutable::now('UTC');
        ActiveTimer::create([
            'task_id'          => $task->id,
            'state'            => ActiveTimer::STATE_FOCUS,
            'cycle_count'      => 0,
            'starts_at'        => $now->subSeconds(30),
            'phase_started_at' => $now->subSeconds(30),
        ]);

        $this->post('/timer/stop')->assertOk();

        $this->assertSame(0, ActiveTimer::count());
        $this->assertSame(0, ManualEntry::count(), 'misclick: <1 min se descarta');
    }

    public function test_pause_then_resume_accumulates_offset(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        ActiveTimer::create([
            'task_id'          => $task->id,
            'state'            => ActiveTimer::STATE_FOCUS,
            'starts_at'        => CarbonImmutable::now('UTC')->subMinutes(2),
            'phase_started_at' => CarbonImmutable::now('UTC')->subMinutes(2),
        ]);

        $this->post('/timer/pause')->assertOk()->assertJson(['running' => true]);
        $this->assertNotNull(ActiveTimer::firstOrFail()->paused_at);

        // Avanzo el reloj 1 minuto antes de reanudar.
        CarbonImmutable::setTestNow(CarbonImmutable::now('UTC')->addMinute());

        $this->post('/timer/resume')->assertOk();
        $t = ActiveTimer::firstOrFail();
        $this->assertNull($t->paused_at);
        $this->assertGreaterThanOrEqual(60, $t->paused_offset_seconds);

        CarbonImmutable::setTestNow();
    }

    public function test_advance_from_focus_creates_entry_and_moves_to_short_break(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        $now  = CarbonImmutable::now('UTC');
        ActiveTimer::create([
            'task_id'          => $task->id,
            'state'            => ActiveTimer::STATE_FOCUS,
            'cycle_count'      => 0,
            'starts_at'        => $now->subMinutes(25),
            'phase_started_at' => $now->subMinutes(25),
        ]);

        $this->post('/timer/advance')->assertOk();

        $timer = ActiveTimer::firstOrFail();
        $this->assertSame(ActiveTimer::STATE_SHORT_BREAK, $timer->state);
        $this->assertSame(1, $timer->cycle_count);
        $this->assertSame(1, ManualEntry::count());
    }

    public function test_advance_after_cycles_until_long_yields_long_break(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        // Forzamos cycles_until_long=2 para no tener que crear 4 focus.
        Setting::set('pomodoro_cycles_until_long', 2);

        $now = CarbonImmutable::now('UTC');
        ActiveTimer::create([
            'task_id'          => $task->id,
            'state'            => ActiveTimer::STATE_FOCUS,
            'cycle_count'      => 1, // este focus completará el segundo
            'starts_at'        => $now->subMinutes(25),
            'phase_started_at' => $now->subMinutes(25),
        ]);

        $this->post('/timer/advance')->assertOk();

        $this->assertSame(ActiveTimer::STATE_LONG_BREAK, ActiveTimer::firstOrFail()->state);
    }

    public function test_advance_from_break_back_to_focus_does_not_create_entry(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        $now  = CarbonImmutable::now('UTC');
        ActiveTimer::create([
            'task_id'          => $task->id,
            'state'            => ActiveTimer::STATE_SHORT_BREAK,
            'cycle_count'      => 1,
            'starts_at'        => $now->subMinutes(10),
            'phase_started_at' => $now->subMinutes(5),
        ]);

        $this->post('/timer/advance')->assertOk();

        $this->assertSame(ActiveTimer::STATE_FOCUS, ActiveTimer::firstOrFail()->state);
        $this->assertSame(0, ManualEntry::count());
    }

    public function test_next_returns_suggested_task_by_priority(): void
    {
        Task::create(['title' => 'Backlog low', 'status' => 'backlog', 'priority' => 'low']);
        $expected = Task::create(['title' => 'Doing high', 'status' => 'doing', 'priority' => 'high']);
        Task::create(['title' => 'Todo high', 'status' => 'todo', 'priority' => 'high']);

        $this->getJson('/timer/next')
            ->assertOk()
            ->assertJson(['task' => ['id' => $expected->id, 'title' => 'Doing high']]);
    }

    public function test_deleting_a_task_clears_its_timer_reference(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        ActiveTimer::create([
            'task_id'          => $task->id,
            'state'            => ActiveTimer::STATE_FOCUS,
            'starts_at'        => CarbonImmutable::now('UTC'),
            'phase_started_at' => CarbonImmutable::now('UTC'),
        ]);

        $task->forceDelete();

        $this->assertSame(1, ActiveTimer::count());
        $this->assertNull(ActiveTimer::firstOrFail()->task_id);
    }

    public function test_focused_ratio_uses_overlapping_timeblocks(): void
    {
        $project = Project::create(['code' => 'P', 'name' => 'P', 'color' => '#000']);
        $task    = Task::create(['title' => 'T', 'status' => 'todo', 'project_id' => $project->id]);

        $start = CarbonImmutable::create(2026, 5, 27, 10, 0, 0, 'UTC');
        $end   = $start->addMinutes(30);

        // 20 de los 30 minutos cayeron en un TimeBlock del proyecto correcto.
        TimeBlock::create([
            'starts_at'           => $start->addMinutes(5),
            'ends_at'             => $start->addMinutes(25),
            'dominant_project_id' => $project->id,
            'confidence'          => 0.9,
            'status'              => 'auto',
            'scoring_snapshot'    => [],
            'generated_at'        => $start,
        ]);

        $ratio = app(PomodoroService::class)->focusedRatio($start, $end, $project->id);
        $this->assertEqualsWithDelta(20 / 30, $ratio, 0.01);
    }
}
