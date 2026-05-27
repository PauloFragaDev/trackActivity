<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskCheckbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskCheckboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_add_a_checkbox_to_a_task(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);

        $response = $this->post("/tasks/{$task->id}/checkboxes", ['title' => 'Subtarea 1'])
            ->assertOk();

        $this->assertSame('Subtarea 1', TaskCheckbox::firstOrFail()->title);
        $this->assertSame('Subtarea 1', $response->json('title'));
    }

    public function test_position_increments_automatically(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        $this->post("/tasks/{$task->id}/checkboxes", ['title' => 'A'])->assertOk();
        $this->post("/tasks/{$task->id}/checkboxes", ['title' => 'B'])->assertOk();

        $positions = TaskCheckbox::orderBy('position')->pluck('position', 'title')->all();
        $this->assertSame(['A' => 0, 'B' => 1], $positions);
    }

    public function test_can_toggle_a_checkbox(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        $cb = TaskCheckbox::create(['task_id' => $task->id, 'title' => 'Item']);

        $this->patch("/tasks/{$task->id}/checkboxes/{$cb->id}", ['checked' => true])
            ->assertOk();

        $this->assertTrue($cb->fresh()->checked);
    }

    public function test_can_delete_a_checkbox(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        $cb = TaskCheckbox::create(['task_id' => $task->id, 'title' => 'Item']);

        $this->delete("/tasks/{$task->id}/checkboxes/{$cb->id}")
            ->assertNoContent();

        $this->assertSame(0, TaskCheckbox::count());
    }

    public function test_a_checkbox_belongs_only_to_its_own_task(): void
    {
        $task1 = Task::create(['title' => 'T1', 'status' => 'todo']);
        $task2 = Task::create(['title' => 'T2', 'status' => 'todo']);
        $cb    = TaskCheckbox::create(['task_id' => $task1->id, 'title' => 'X']);

        // Pasar el checkbox bajo la URL de la OTRA tarea → 404.
        $this->patch("/tasks/{$task2->id}/checkboxes/{$cb->id}", ['checked' => true])
            ->assertNotFound();
    }

    public function test_deleting_a_task_cascades_its_checkboxes(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        TaskCheckbox::create(['task_id' => $task->id, 'title' => 'X']);

        $task->forceDelete();   // forzar el delete real (soft-delete no cascada)

        $this->assertSame(0, TaskCheckbox::count());
    }
}
