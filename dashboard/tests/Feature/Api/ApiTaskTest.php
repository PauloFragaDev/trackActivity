<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTaskTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): array
    {
        config(['app.api_token' => 'secret']);
        return ['Authorization' => 'Bearer secret'];
    }

    public function test_index_lists_active_tasks_with_relations(): void
    {
        $project = Project::create(['code' => 'PRJ', 'name' => 'Proyecto', 'color' => '#10b981']);
        $label   = TaskLabel::create(['title' => 'urgent', 'color' => '#e11d48', 'position' => 0]);
        $task    = Task::create(['title' => 'T1', 'status' => 'doing', 'project_id' => $project->id]);
        $task->labels()->sync([$label->id]);

        // Archivada — NO debería aparecer por defecto.
        Task::create(['title' => 'Vieja', 'status' => 'done'])->delete();

        $res = $this->withHeaders($this->auth())->getJson('/api/tasks');

        $res->assertOk()->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.title',          'T1');
        $res->assertJsonPath('data.0.status',         'doing');
        $res->assertJsonPath('data.0.project.code',   'PRJ');
        $res->assertJsonPath('data.0.labels.0.title', 'urgent');
    }

    public function test_index_filters_by_project_status_and_since(): void
    {
        $a = Project::create(['code' => 'A', 'name' => 'A', 'color' => '#000']);
        $b = Project::create(['code' => 'B', 'name' => 'B', 'color' => '#000']);

        Task::create(['title' => 'En A doing',   'status' => 'doing',  'project_id' => $a->id]);
        Task::create(['title' => 'En A backlog', 'status' => 'backlog','project_id' => $a->id]);
        Task::create(['title' => 'En B doing',   'status' => 'doing',  'project_id' => $b->id]);

        $res = $this->withHeaders($this->auth())
            ->getJson('/api/tasks?project=' . $a->id . '&status=doing');
        $res->assertOk()->assertJsonCount(1, 'data');
        $res->assertJsonPath('data.0.title', 'En A doing');
    }

    public function test_index_includes_archived_when_requested(): void
    {
        Task::create(['title' => 'Viva',     'status' => 'todo']);
        $gone = Task::create(['title' => 'Archi', 'status' => 'todo']);
        $gone->delete();

        $res = $this->withHeaders($this->auth())->getJson('/api/tasks?include_archived=1');
        $res->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_store_creates_a_task_and_returns_201(): void
    {
        $project = Project::create(['code' => 'P', 'name' => 'P', 'color' => '#000']);

        $payload = [
            'title'       => 'Nueva',
            'description' => 'desc',
            'status'      => 'blocked',
            'priority'    => 'high',
            'project_id'  => $project->id,
            'due_date'    => '2026-06-01',
        ];

        $this->withHeaders($this->auth())->postJson('/api/tasks', $payload)
            ->assertCreated()
            ->assertJsonPath('data.title',    'Nueva')
            ->assertJsonPath('data.status',   'blocked')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.due_date', '2026-06-01');

        $this->assertDatabaseHas('tasks', ['title' => 'Nueva', 'status' => 'blocked']);
    }

    public function test_store_rejects_invalid_status(): void
    {
        $this->withHeaders($this->auth())->postJson('/api/tasks', [
            'title' => 'X', 'status' => 'nope',
        ])->assertStatus(422)->assertJsonValidationErrorFor('status');
    }

    public function test_store_requires_title_and_status(): void
    {
        $this->withHeaders($this->auth())->postJson('/api/tasks', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'status']);
    }

    public function test_show_returns_a_single_task(): void
    {
        $task = Task::create(['title' => 'X', 'status' => 'doing']);

        $this->withHeaders($this->auth())->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.title', 'X');
    }

    public function test_update_patches_fields(): void
    {
        $task = Task::create(['title' => 'Orig', 'status' => 'todo']);

        $this->withHeaders($this->auth())
            ->patchJson("/api/tasks/{$task->id}", ['title' => 'Cambiado', 'status' => 'standby'])
            ->assertOk()
            ->assertJsonPath('data.title',  'Cambiado')
            ->assertJsonPath('data.status', 'standby');
    }

    public function test_destroy_archives_task_with_204(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);

        $this->withHeaders($this->auth())->deleteJson("/api/tasks/{$task->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_restore_brings_back_archived(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        $task->delete();

        $this->withHeaders($this->auth())->postJson("/api/tasks/{$task->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.id', $task->id);
        $this->assertNotSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_force_destroy_only_works_on_archived(): void
    {
        $alive = Task::create(['title' => 'Viva', 'status' => 'todo']);
        $this->withHeaders($this->auth())
            ->deleteJson("/api/tasks/{$alive->id}/force")
            ->assertNotFound();

        $dead = Task::create(['title' => 'Muerta', 'status' => 'todo']);
        $dead->delete();
        $this->withHeaders($this->auth())
            ->deleteJson("/api/tasks/{$dead->id}/force")
            ->assertNoContent();
        $this->assertDatabaseMissing('tasks', ['id' => $dead->id]);
    }

    public function test_projects_endpoint_returns_catalog(): void
    {
        Project::create(['code' => 'A', 'name' => 'Alfa', 'color' => '#10b981']);
        Project::create(['code' => 'B', 'name' => 'Beta', 'color' => '#3b82f6']);

        $this->withHeaders($this->auth())->getJson('/api/projects')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'A');
    }

    public function test_labels_endpoint_returns_catalog(): void
    {
        TaskLabel::create(['title' => 'bug',  'color' => '#e11d48', 'position' => 0]);
        TaskLabel::create(['title' => 'feat', 'color' => '#10b981', 'position' => 1]);

        $this->withHeaders($this->auth())->getJson('/api/task-labels')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
