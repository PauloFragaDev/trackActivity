<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_a_comment(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);

        $response = $this->post("/tasks/{$task->id}/comments", ['body' => 'Recordatorio'])
            ->assertOk();

        $this->assertSame('Recordatorio', TaskComment::firstOrFail()->body);
        $this->assertSame('Recordatorio', $response->json('body'));
        $this->assertNotNull($response->json('created_at'));
    }

    public function test_body_is_required(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);

        $this->post("/tasks/{$task->id}/comments", ['body' => ''])
            ->assertSessionHasErrors('body');
    }

    public function test_can_delete_a_comment(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        $c    = TaskComment::create(['task_id' => $task->id, 'body' => 'X']);

        $this->delete("/tasks/{$task->id}/comments/{$c->id}")->assertNoContent();
        $this->assertSame(0, TaskComment::count());
    }

    public function test_a_comment_only_belongs_to_its_own_task(): void
    {
        $task1 = Task::create(['title' => 'T1', 'status' => 'todo']);
        $task2 = Task::create(['title' => 'T2', 'status' => 'todo']);
        $c     = TaskComment::create(['task_id' => $task1->id, 'body' => 'X']);

        $this->delete("/tasks/{$task2->id}/comments/{$c->id}")->assertNotFound();
    }

    public function test_deleting_a_task_cascades_its_comments(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        TaskComment::create(['task_id' => $task->id, 'body' => 'X']);

        $task->forceDelete();

        $this->assertSame(0, TaskComment::count());
    }
}
