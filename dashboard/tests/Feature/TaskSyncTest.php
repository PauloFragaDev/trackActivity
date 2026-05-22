<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\GitHub\TaskSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeProjectClient;
use Tests\TestCase;

class TaskSyncTest extends TestCase
{
    use RefreshDatabase;

    private function item(string $id, string $title, string $status, string $body = ''): array
    {
        return [
            'id'        => $id,
            'updatedAt' => '2026-05-22T10:00:00Z',
            'title'     => $title,
            'body'      => $body,
            'status'    => $status,
            'isDraft'   => true,
        ];
    }

    public function test_pull_creates_a_task_from_a_remote_item(): void
    {
        $fake = new FakeProjectClient();
        $fake->items = [$this->item('I1', 'Tarea remota', 'In Progress', 'cuerpo')];

        $result = (new TaskSyncService($fake))->sync();

        $this->assertSame(1, $result['created']);
        $task = Task::firstOrFail();
        $this->assertSame('Tarea remota', $task->title);
        $this->assertSame('I1', $task->github_item_id);
        $this->assertSame(TaskStatus::Doing, $task->status);   // "In Progress" => doing
    }

    public function test_pull_updates_an_existing_linked_task(): void
    {
        $task = Task::create(['title' => 'Viejo', 'status' => 'todo', 'github_item_id' => 'I1']);

        $fake = new FakeProjectClient();
        $fake->items = [$this->item('I1', 'Nuevo título', 'Done')];

        $result = (new TaskSyncService($fake))->sync();

        $this->assertSame(1, $result['updated']);
        $task->refresh();
        $this->assertSame('Nuevo título', $task->title);
        $this->assertSame(TaskStatus::Done, $task->status);
    }

    public function test_pull_removes_tasks_whose_item_vanished(): void
    {
        Task::create(['title' => 'Fantasma', 'status' => 'todo', 'github_item_id' => 'GONE']);

        $fake = new FakeProjectClient();
        $fake->items = [$this->item('I1', 'Viva', 'Todo')];

        $result = (new TaskSyncService($fake))->sync();

        $this->assertSame(1, $result['removed']);
        $this->assertDatabaseMissing('tasks', ['github_item_id' => 'GONE']);
    }

    public function test_sync_command_is_a_noop_when_not_configured(): void
    {
        $this->artisan('tasks:sync')
            ->expectsOutputToContain('no configurada')
            ->assertSuccessful();
    }
}
