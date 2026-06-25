<?php

namespace Tests\Feature;

use App\Models\TeamTask;
use App\Models\TeamProject;
use App\Enums\TaskStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTaskControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ejecutar migraciones del equipo también en la conexión supabase (SQLite :memory: en tests)
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
    }

    public function test_index_renders_team_board(): void
    {
        TeamTask::create(['title' => 'Tarea equipo', 'status' => 'todo', 'position' => 0]);

        $this->get('/team/tasks')->assertOk()->assertSee('Tarea equipo');
    }

    public function test_store_creates_team_task(): void
    {
        $this->post('/team/tasks', [
            '_token' => csrf_token(),
            'title'  => 'Nueva tarea',
            'status' => 'todo',
        ])->assertRedirect('/team/tasks');

        $this->assertDatabaseHas('tasks', ['title' => 'Nueva tarea'], 'supabase');
    }

    public function test_move_changes_status(): void
    {
        $task = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);

        $this->patch("/team/tasks/{$task->id}/move", [
            '_token'   => csrf_token(),
            '_method'  => 'PATCH',
            'status'   => 'doing',
            'position' => 0,
        ])->assertJson(['ok' => true]);

        $this->assertEquals('doing', TeamTask::find($task->id)->status->value);
    }

    public function test_destroy_soft_deletes_task(): void
    {
        $task = TeamTask::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);

        $this->delete("/team/tasks/{$task->id}")->assertRedirect('/team/tasks');

        $this->assertSoftDeleted('tasks', ['id' => $task->id], 'supabase');
    }
}
