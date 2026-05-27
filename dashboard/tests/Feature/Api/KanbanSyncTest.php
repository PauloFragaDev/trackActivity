<?php

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\ProjectMapping;
use App\Models\Task;
use App\Models\TaskLabel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanSyncTest extends TestCase
{
    use RefreshDatabase;

    private function auth(): array
    {
        config(['app.api_token' => 'secret']);
        return ['Authorization' => 'Bearer secret'];
    }

    /** Crea proyecto + mapping folder apuntando al workspace_path indicado. */
    private function makeProjectAt(string $workspacePath, string $code = 'PRJ'): Project
    {
        $project = Project::create(['code' => $code, 'name' => $code, 'color' => '#10b981']);
        ProjectMapping::create([
            'project_id' => $project->id,
            'type'       => 'folder',
            'pattern'    => $workspacePath,
            'is_regex'   => false,
            'enabled'    => true,
        ]);
        return $project;
    }

    private function payload(string $workspacePath, array $lists, ?string $clientAt = null): array
    {
        return [
            'workspace_path'    => $workspacePath,
            'client_updated_at' => $clientAt ?? now()->toIso8601String(),
            'lists'             => $lists,
        ];
    }

    public function test_returns_422_when_no_project_mapping_found(): void
    {
        $payload = $this->payload('/no/match', [
            ['title' => 'Backlog', 'cards' => []],
        ]);

        $this->withHeaders($this->auth())->postJson('/api/sync/kanban', $payload)
            ->assertStatus(422)
            ->assertJsonPath('error', 'no_project_mapping');
    }

    public function test_creates_new_tasks_from_unseen_cards(): void
    {
        $project = $this->makeProjectAt('/repo/myapp');

        $payload = $this->payload('/repo/myapp', [
            ['title' => 'Backlog', 'cards' => [
                ['id' => 'card-1', 'title' => 'Pendiente 1', 'updated_at' => now()->toIso8601String()],
            ]],
            ['title' => 'Doing',   'cards' => [
                ['id' => 'card-2', 'title' => 'En curso', 'description' => '# md', 'updated_at' => now()->toIso8601String()],
            ]],
        ]);

        $res = $this->withHeaders($this->auth())->postJson('/api/sync/kanban', $payload);
        $res->assertOk();
        $res->assertJsonPath('stats.created', 2);
        $res->assertJsonPath('project.id', $project->id);

        $this->assertDatabaseHas('tasks', [
            'kanban_card_id' => 'card-1', 'title' => 'Pendiente 1', 'status' => 'backlog', 'project_id' => $project->id,
        ]);
        $this->assertDatabaseHas('tasks', [
            'kanban_card_id' => 'card-2', 'title' => 'En curso', 'status' => 'doing',
        ]);
    }

    public function test_client_wins_when_card_updated_after_server(): void
    {
        $project = $this->makeProjectAt('/repo/x');
        $task = Task::create([
            'title'            => 'Antiguo',
            'status'           => 'todo',
            'project_id'       => $project->id,
            'kanban_card_id'   => 'card-1',
            'kanban_synced_at' => CarbonImmutable::parse('2026-05-01T10:00:00Z'),
        ]);
        // Marcamos updated_at del server explícitamente "antiguo".
        Task::where('id', $task->id)->update(['updated_at' => '2026-05-01 10:00:00']);

        $payload = $this->payload('/repo/x', [
            ['title' => 'Doing', 'cards' => [
                ['id' => 'card-1', 'title' => 'Renombrado', 'updated_at' => '2026-05-27T10:00:00Z'],
            ]],
        ]);

        $this->withHeaders($this->auth())->postJson('/api/sync/kanban', $payload)
            ->assertOk()
            ->assertJsonPath('stats.updated_local', 1);

        $task->refresh();
        $this->assertSame('Renombrado', $task->title);
        $this->assertSame('doing', $task->status->value);
    }

    public function test_server_wins_when_card_updated_before_server(): void
    {
        $project = $this->makeProjectAt('/repo/y');
        $task = Task::create([
            'title'          => 'Versión server',
            'status'         => 'doing',
            'project_id'     => $project->id,
            'kanban_card_id' => 'card-1',
        ]);
        Task::where('id', $task->id)->update(['updated_at' => '2026-05-27 10:00:00']);

        $payload = $this->payload('/repo/y', [
            ['title' => 'Backlog', 'cards' => [
                ['id' => 'card-1', 'title' => 'Versión vieja del cliente', 'updated_at' => '2026-05-01T10:00:00Z'],
            ]],
        ]);

        $res = $this->withHeaders($this->auth())->postJson('/api/sync/kanban', $payload);
        $res->assertOk()->assertJsonPath('stats.kept_server', 1);

        $task->refresh();
        $this->assertSame('Versión server', $task->title);
        $this->assertSame('doing', $task->status->value);
    }

    public function test_archives_cards_missing_from_payload(): void
    {
        $project = $this->makeProjectAt('/repo/z');
        Task::create([
            'title'          => 'Sobrevive',
            'status'         => 'todo',
            'project_id'     => $project->id,
            'kanban_card_id' => 'card-keep',
        ]);
        $gone = Task::create([
            'title'          => 'Va al archivo',
            'status'         => 'todo',
            'project_id'     => $project->id,
            'kanban_card_id' => 'card-drop',
        ]);

        $payload = $this->payload('/repo/z', [
            ['title' => 'Backlog', 'cards' => [
                ['id' => 'card-keep', 'title' => 'Sobrevive', 'updated_at' => now()->subYear()->toIso8601String()],
            ]],
        ]);

        $this->withHeaders($this->auth())->postJson('/api/sync/kanban', $payload)
            ->assertOk()
            ->assertJsonPath('stats.archived', 1);

        $this->assertSoftDeleted('tasks', ['id' => $gone->id]);
        $this->assertNotSoftDeleted('tasks', ['kanban_card_id' => 'card-keep']);
    }

    public function test_labels_are_created_when_unknown(): void
    {
        $project = $this->makeProjectAt('/repo/labels');

        $payload = $this->payload('/repo/labels', [
            ['title' => 'Backlog', 'cards' => [
                ['id' => 'c-1', 'title' => 'Con labels', 'updated_at' => now()->toIso8601String(), 'labels' => [
                    ['title' => 'urgent', 'color' => '#e11d48'],
                    ['title' => 'spike',  'color' => '#3b82f6'],
                ]],
            ]],
        ]);

        $this->withHeaders($this->auth())->postJson('/api/sync/kanban', $payload)->assertOk();

        $task = Task::where('kanban_card_id', 'c-1')->firstOrFail();
        $this->assertSame(['spike', 'urgent'], $task->labels->pluck('title')->sort()->values()->all());
        $this->assertDatabaseHas('task_labels', ['title' => 'urgent', 'color' => '#e11d48']);
    }

    public function test_labels_match_existing_by_title_case_insensitive(): void
    {
        $project = $this->makeProjectAt('/repo/match');
        $existing = TaskLabel::create(['title' => 'Urgent', 'color' => '#aa0000', 'position' => 0]);

        $payload = $this->payload('/repo/match', [
            ['title' => 'Backlog', 'cards' => [
                ['id' => 'c-1', 'title' => 'T', 'updated_at' => now()->toIso8601String(), 'labels' => [
                    ['title' => 'urgent'],  // lowercase
                ]],
            ]],
        ]);

        $this->withHeaders($this->auth())->postJson('/api/sync/kanban', $payload)->assertOk();
        // No se crea un duplicado.
        $this->assertSame(1, TaskLabel::count());
        $task = Task::where('kanban_card_id', 'c-1')->firstOrFail();
        $this->assertSame([$existing->id], $task->labels->pluck('id')->all());
    }

    public function test_unknown_column_collects_an_error_and_skips_cards(): void
    {
        $project = $this->makeProjectAt('/repo/q');

        $payload = $this->payload('/repo/q', [
            ['title' => 'Inventada', 'cards' => [
                ['id' => 'c-1', 'title' => 'no debería entrar', 'updated_at' => now()->toIso8601String()],
            ]],
            ['title' => 'Backlog', 'cards' => [
                ['id' => 'c-2', 'title' => 'sí', 'updated_at' => now()->toIso8601String()],
            ]],
        ]);

        $res = $this->withHeaders($this->auth())->postJson('/api/sync/kanban', $payload);
        $res->assertOk();
        $res->assertJsonPath('stats.created', 1);
        $this->assertCount(1, $res->json('errors'));

        $this->assertDatabaseMissing('tasks', ['kanban_card_id' => 'c-1']);
        $this->assertDatabaseHas('tasks', ['kanban_card_id' => 'c-2']);
    }

    public function test_column_matching_is_case_insensitive_and_tolerates_spaces(): void
    {
        $project = $this->makeProjectAt('/repo/r');

        $payload = $this->payload('/repo/r', [
            ['title' => 'to do',    'cards' => [
                ['id' => 'a', 'title' => 'a', 'updated_at' => now()->toIso8601String()],
            ]],
            ['title' => 'standby',  'cards' => [
                ['id' => 'b', 'title' => 'b', 'updated_at' => now()->toIso8601String()],
            ]],
            ['title' => 'STAND BY', 'cards' => [
                ['id' => 'c', 'title' => 'c', 'updated_at' => now()->toIso8601String()],
            ]],
        ]);

        $this->withHeaders($this->auth())->postJson('/api/sync/kanban', $payload)->assertOk();

        $this->assertSame('todo',    Task::where('kanban_card_id', 'a')->firstOrFail()->status->value);
        $this->assertSame('standby', Task::where('kanban_card_id', 'b')->firstOrFail()->status->value);
        $this->assertSame('standby', Task::where('kanban_card_id', 'c')->firstOrFail()->status->value);
    }

    public function test_response_lists_full_state_in_six_columns(): void
    {
        $project = $this->makeProjectAt('/repo/full');

        $payload = $this->payload('/repo/full', [
            ['title' => 'Doing', 'cards' => [
                ['id' => 'c-1', 'title' => 'A', 'updated_at' => now()->toIso8601String()],
            ]],
        ]);

        $res = $this->withHeaders($this->auth())->postJson('/api/sync/kanban', $payload);
        $res->assertOk();

        // El server siempre devuelve las 6 columnas, en orden, aunque las
        // demás estén vacías.
        $lists = $res->json('lists');
        $this->assertCount(6, $lists);
        $this->assertSame('Blocked',  $lists[0]['title']);
        $this->assertSame('Backlog',  $lists[1]['title']);
        $this->assertSame('To Do',    $lists[2]['title']);
        $this->assertSame('Doing',    $lists[3]['title']);
        $this->assertSame('Stand By', $lists[4]['title']);
        $this->assertSame('Done',     $lists[5]['title']);
        $this->assertCount(1, $lists[3]['cards']);
        $this->assertSame('A', $lists[3]['cards'][0]['title']);
    }

    public function test_requires_auth(): void
    {
        config(['app.api_token' => 'secret']);
        $this->postJson('/api/sync/kanban', $this->payload('/x', []))->assertStatus(401);
    }
}
