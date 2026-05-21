<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_loads(): void
    {
        $this->get('/tasks')->assertOk();
    }

    public function test_store_creates_a_task(): void
    {
        $this->post('/tasks', ['title' => 'Mi tarea', 'status' => 'todo'])->assertRedirect();

        $task = Task::firstOrFail();
        $this->assertSame('Mi tarea', $task->title);
        $this->assertSame(TaskStatus::Todo, $task->status);
    }

    public function test_store_requires_title(): void
    {
        $this->post('/tasks', ['status' => 'todo'])->assertSessionHasErrors('title');
    }

    public function test_update_edits_a_task(): void
    {
        $task = Task::create(['title' => 'Original', 'status' => 'todo']);

        $this->patch("/tasks/{$task->id}", ['title' => 'Editada', 'status' => 'doing'])->assertRedirect();

        $task->refresh();
        $this->assertSame('Editada', $task->title);
        $this->assertSame(TaskStatus::Doing, $task->status);
    }

    public function test_done_sets_and_clears_completed_at(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        $this->assertNull($task->completed_at);

        $this->patch("/tasks/{$task->id}", ['title' => 'T', 'status' => 'done'])->assertRedirect();
        $this->assertNotNull($task->fresh()->completed_at);

        $this->patch("/tasks/{$task->id}", ['title' => 'T', 'status' => 'todo'])->assertRedirect();
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_destroy_deletes_a_task(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);

        $this->delete("/tasks/{$task->id}")->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }
}
