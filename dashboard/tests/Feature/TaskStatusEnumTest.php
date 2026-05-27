<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\PomodoroService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskStatusEnumTest extends TestCase
{
    use RefreshDatabase;

    public function test_enum_cases_in_expected_order(): void
    {
        $expected = ['blocked', 'backlog', 'todo', 'doing', 'standby', 'done'];
        $actual = array_map(fn (TaskStatus $s) => $s->value, TaskStatus::cases());
        $this->assertSame($expected, $actual);
    }

    public function test_labels_match_code_kanban_set(): void
    {
        $this->assertSame('Blocked',  TaskStatus::Blocked->label());
        $this->assertSame('Backlog',  TaskStatus::Backlog->label());
        $this->assertSame('To Do',    TaskStatus::Todo->label());
        $this->assertSame('Doing',    TaskStatus::Doing->label());
        $this->assertSame('Stand By', TaskStatus::StandBy->label());
        $this->assertSame('Done',     TaskStatus::Done->label());
    }

    public function test_actionable_subset_excludes_blocked_standby_done(): void
    {
        $this->assertTrue(TaskStatus::Doing->isActionable());
        $this->assertTrue(TaskStatus::Todo->isActionable());
        $this->assertTrue(TaskStatus::Backlog->isActionable());
        $this->assertFalse(TaskStatus::Blocked->isActionable());
        $this->assertFalse(TaskStatus::StandBy->isActionable());
        $this->assertFalse(TaskStatus::Done->isActionable());
    }

    public function test_pomodoro_next_task_skips_blocked_and_standby(): void
    {
        // Una blocked con prioridad high — no debería ganar.
        Task::create(['title' => 'Bloqueada',  'status' => 'blocked', 'priority' => 'high']);
        Task::create(['title' => 'En espera',  'status' => 'standby', 'priority' => 'high']);
        // La candidata real: Doing aunque sin prioridad.
        $doing = Task::create(['title' => 'Activa', 'status' => 'doing']);

        $next = app(PomodoroService::class)->nextTask();
        $this->assertSame($doing->id, $next->id);
    }
}
