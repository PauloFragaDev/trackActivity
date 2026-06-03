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

    public function test_store_stamps_author_from_settings(): void
    {
        \App\Services\UserIdentity::setName('Paulo');
        $token = \App\Services\UserIdentity::token();
        $task  = Task::create(['title' => 'T', 'status' => 'todo']);

        $res = $this->post("/tasks/{$task->id}/comments", ['body' => 'Hola'])->assertOk();

        $res->assertJson(['author_name' => 'Paulo', 'author_token' => $token]);
        $c = TaskComment::firstOrFail();
        $this->assertSame('Paulo', $c->author_name);
        $this->assertSame($token, $c->author_token);
    }

    public function test_store_without_name_leaves_author_name_null(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);

        $this->post("/tasks/{$task->id}/comments", ['body' => 'Hola'])->assertOk();

        $c = TaskComment::firstOrFail();
        $this->assertNull($c->author_name);
        $this->assertNotEmpty($c->author_token);   // el token siempre se sella
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
