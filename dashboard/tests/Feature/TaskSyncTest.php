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

    /** Un item remoto del Project. */
    private function item(string $id, string $title, string $status, string $body = ''): array
    {
        return [
            'id'        => $id,
            'contentId' => 'content-' . $id,
            'updatedAt' => '2026-05-22T10:00:00Z',
            'title'     => $title,
            'body'      => $body,
            'status'    => $status,
            'isDraft'   => true,
        ];
    }

    /** Una tarea local ya sincronizada (sin cambios pendientes de subir). */
    private function syncedTask(array $attrs): Task
    {
        return Task::create([...$attrs, 'github_synced_at' => now()]);
    }

    // ── PULL ───────────────────────────────────────────────

    public function test_pull_creates_a_task_from_a_remote_item(): void
    {
        $fake = new FakeProjectClient();
        $fake->items = [$this->item('I1', 'Tarea remota', 'In Progress', 'cuerpo')];

        $result = (new TaskSyncService($fake))->sync();

        $this->assertSame(1, $result['created']);
        $task = Task::firstOrFail();
        $this->assertSame('Tarea remota', $task->title);
        $this->assertSame('I1', $task->github_item_id);
        $this->assertSame(TaskStatus::Doing, $task->status);
    }

    public function test_pull_updates_a_synced_task(): void
    {
        $task = $this->syncedTask(['title' => 'Viejo', 'status' => 'todo', 'github_item_id' => 'I1']);

        $fake = new FakeProjectClient();
        $fake->items = [$this->item('I1', 'Nuevo título', 'Done')];

        $result = (new TaskSyncService($fake))->sync();

        $this->assertSame(1, $result['updated']);
        $task->refresh();
        $this->assertSame('Nuevo título', $task->title);
        $this->assertSame(TaskStatus::Done, $task->status);
        $this->assertEmpty($fake->updated);   // no se subió nada: la tarea estaba limpia
    }

    public function test_pull_removes_tasks_whose_item_vanished(): void
    {
        $this->syncedTask(['title' => 'Fantasma', 'status' => 'todo', 'github_item_id' => 'GONE']);

        $fake = new FakeProjectClient();
        $fake->items = [$this->item('I1', 'Viva', 'Todo')];

        $result = (new TaskSyncService($fake))->sync();

        $this->assertSame(1, $result['removed']);
        $this->assertDatabaseMissing('tasks', ['github_item_id' => 'GONE']);
    }

    // ── PUSH ───────────────────────────────────────────────

    public function test_push_creates_a_draft_for_a_new_local_task(): void
    {
        $task = Task::create(['title' => 'Tarea local', 'description' => 'desc', 'status' => 'doing']);

        $fake   = new FakeProjectClient();
        $result = (new TaskSyncService($fake))->sync();

        $this->assertSame(1, $result['pushed']);
        $this->assertCount(1, $fake->created);
        $this->assertSame('Tarea local', $fake->created[0]['title']);

        $task->refresh();
        $this->assertNotNull($task->github_item_id);
        $this->assertNotNull($task->github_synced_at);
    }

    public function test_push_updates_a_dirty_linked_task(): void
    {
        Task::create([
            'title'          => 'Editada localmente',
            'status'         => 'todo',
            'github_item_id' => 'I1',
            'github_synced_at' => now(),
            'github_dirty'   => true,   // cambios locales pendientes de subir
        ]);

        $fake = new FakeProjectClient();
        $fake->items = [$this->item('I1', 'Original', 'Todo')];

        $result = (new TaskSyncService($fake))->sync();

        $this->assertSame(1, $result['pushed']);
        $this->assertCount(1, $fake->updated);
        $this->assertSame('Editada localmente', $fake->updated[0]['title']);
    }

    public function test_push_deletes_the_remote_item_for_a_deleted_task(): void
    {
        $task = $this->syncedTask(['title' => 'Para borrar', 'status' => 'todo', 'github_item_id' => 'I1']);
        $task->delete();   // borrado suave

        $fake = new FakeProjectClient();
        $fake->items = [$this->item('I1', 'Para borrar', 'Todo')];

        (new TaskSyncService($fake))->sync();

        $this->assertSame(['I1'], $fake->deleted);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);   // purgada de verdad
    }

    public function test_sync_command_is_a_noop_when_not_configured(): void
    {
        $this->artisan('tasks:sync')
            ->expectsOutputToContain('no configurada')
            ->assertSuccessful();
    }
}
