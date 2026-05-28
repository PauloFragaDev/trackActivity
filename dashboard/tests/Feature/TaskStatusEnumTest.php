<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
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

}
