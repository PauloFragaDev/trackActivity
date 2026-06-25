<?php

namespace Tests\Feature;

use App\Models\TeamTask;
use App\Models\TeamTaskCheckbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTaskCheckboxControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
    }

    public function test_store_creates_checkbox(): void
    {
        $task = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);

        $this->postJson("/team/tasks/{$task->id}/checkboxes", ['title' => 'Subtarea nueva'])
            ->assertJsonFragment(['title' => 'Subtarea nueva', 'checked' => false]);

        $this->assertDatabaseHas('task_checkboxes', ['task_id' => $task->id, 'title' => 'Subtarea nueva'], 'supabase');
    }

    public function test_update_toggles_checkbox(): void
    {
        $task     = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);
        $checkbox = TeamTaskCheckbox::create(['task_id' => $task->id, 'title' => 'CB', 'checked' => false, 'position' => 0]);

        $this->patchJson("/team/tasks/{$task->id}/checkboxes/{$checkbox->id}", ['checked' => true])
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('task_checkboxes', ['id' => $checkbox->id, 'checked' => true], 'supabase');
    }

    public function test_destroy_deletes_checkbox(): void
    {
        $task     = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);
        $checkbox = TeamTaskCheckbox::create(['task_id' => $task->id, 'title' => 'CB', 'checked' => false, 'position' => 0]);

        $this->delete("/team/tasks/{$task->id}/checkboxes/{$checkbox->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('task_checkboxes', ['id' => $checkbox->id], 'supabase');
    }
}
