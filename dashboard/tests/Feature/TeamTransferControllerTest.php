<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TaskCheckbox;
use App\Models\TaskComment;
use App\Models\TaskLabel;
use App\Models\TeamMember;
use App\Models\TeamProject;
use App\Models\TeamTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamTransferControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('modules.team', true);
        $this->artisan('migrate', ['--database' => 'supabase', '--path' => 'database/migrations/team']);
        $member = TeamMember::create(['name' => 'Ana', 'color' => '#000', 'position' => 0]);
        session(['team_member_id' => $member->id, 'team_member_name' => 'Ana']);
    }

    public function test_preview_returns_project_not_exists(): void
    {
        $project = Project::create(['code' => 'ALFA', 'name' => 'Alfa']);
        $task    = Task::create(['title' => 'Mi tarea', 'status' => 'todo', 'position' => 0, 'project_id' => $project->id]);

        $this->getJson("/tasks/{$task->id}/transfer-preview")
            ->assertJson(['project' => ['code' => 'ALFA', 'exists' => false]]);
    }

    public function test_preview_returns_project_exists(): void
    {
        $project     = Project::create(['code' => 'ALFA', 'name' => 'Alfa']);
        TeamProject::create(['code' => 'ALFA', 'name' => 'Alfa']);
        $task = Task::create(['title' => 'T', 'status' => 'todo', 'position' => 0, 'project_id' => $project->id]);

        $this->getJson("/tasks/{$task->id}/transfer-preview")
            ->assertJson(['project' => ['code' => 'ALFA', 'exists' => true]]);
    }

    public function test_transfer_copies_task_to_team_and_archives_original(): void
    {
        $task = Task::create(['title' => 'Vacaciones task', 'status' => 'doing', 'priority' => 'high', 'position' => 0]);

        $this->postJson("/tasks/{$task->id}/transfer-to-team")
            ->assertJson(['ok' => true]);

        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('tasks', ['title' => 'Vacaciones task', 'status' => 'doing'], 'supabase');
    }

    public function test_transfer_copies_checkboxes_and_comments(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);
        TaskCheckbox::create(['task_id' => $task->id, 'title' => 'Subtarea 1', 'checked' => false, 'position' => 0]);
        TaskComment::create(['task_id' => $task->id, 'body' => 'Un comentario', 'author_name' => 'Bob']);

        $response = $this->postJson("/tasks/{$task->id}/transfer-to-team")->assertJson(['ok' => true]);
        $teamTaskId = $response->json('team_task_id');

        $this->assertDatabaseHas('task_checkboxes', ['task_id' => $teamTaskId, 'title' => 'Subtarea 1'], 'supabase');
        $this->assertDatabaseHas('task_comments',   ['task_id' => $teamTaskId, 'body' => 'Un comentario'], 'supabase');
    }

    public function test_transfer_creates_team_project_if_missing(): void
    {
        $project = Project::create(['code' => 'NUEVO', 'name' => 'Nuevo', 'color' => '#abc']);
        $task    = Task::create(['title' => 'T', 'status' => 'todo', 'position' => 0, 'project_id' => $project->id]);

        $this->postJson("/tasks/{$task->id}/transfer-to-team")->assertJson(['ok' => true]);

        $this->assertDatabaseHas('projects', ['code' => 'NUEVO'], 'supabase');
    }

    public function test_transfer_blocked_when_team_disabled(): void
    {
        Setting::set('modules.team', false);
        $task = Task::create(['title' => 'T', 'status' => 'todo', 'position' => 0]);

        $this->postJson("/tasks/{$task->id}/transfer-to-team")->assertStatus(403);
    }
}
