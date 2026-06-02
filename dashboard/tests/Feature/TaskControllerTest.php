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

        // Borrado suave: la fila se conserva hasta que la sync propaga el borrado.
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_move_changes_column_and_reindexes(): void
    {
        $a = Task::create(['title' => 'A', 'status' => 'todo', 'position' => 0]);
        $b = Task::create(['title' => 'B', 'status' => 'todo', 'position' => 1]);

        $this->patch("/tasks/{$b->id}/move", ['status' => 'doing', 'position' => 0])
            ->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(TaskStatus::Doing, $b->fresh()->status);
        $this->assertSame(0, $b->fresh()->position);
        // La columna de origen se reindexa: A queda en posición 0.
        $this->assertSame(0, $a->fresh()->position);
    }

    public function test_move_to_done_sets_completed_at(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);

        $this->patch("/tasks/{$task->id}/move", ['status' => 'done', 'position' => 0])->assertOk();

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_logged_minutes_sum_the_linked_manual_entries(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        \App\Models\ManualEntry::create([
            'starts_at' => '2026-05-21 09:00:00',
            'ends_at'   => '2026-05-21 10:30:00',
            'kind'      => 'meeting',
            'title'     => 'Reunión',
            'task_id'   => $task->id,
        ]);

        $this->assertSame(90, $task->fresh()->loggedMinutes());
    }

    public function test_inline_add_only_requires_title_and_status(): void
    {
        $this->post('/tasks', ['title' => 'Inline tarea', 'status' => 'todo'])
            ->assertRedirect();

        $task = Task::firstOrFail();
        $this->assertSame('Inline tarea', $task->title);
        $this->assertSame(TaskStatus::Todo, $task->status);
        $this->assertNull($task->priority);
        $this->assertNull($task->project_id);
    }

    public function test_archived_view_lists_only_trashed_tasks(): void
    {
        $kept   = Task::create(['title' => 'Activa',   'status' => 'todo']);
        $gone   = Task::create(['title' => 'Vieja',    'status' => 'todo']);
        $gone->delete();

        $res = $this->get('/tasks/archived')->assertOk();
        $res->assertSee('Vieja');
        $res->assertDontSee('Activa');
    }

    public function test_archived_tasks_do_not_appear_in_board(): void
    {
        $gone = Task::create(['title' => 'No verás esto', 'status' => 'todo']);
        $gone->delete();

        $this->get('/tasks')->assertOk()->assertDontSee('No verás esto');
    }

    public function test_restore_brings_back_a_task(): void
    {
        $task = Task::create(['title' => 'Vuelve', 'status' => 'todo']);
        $task->delete();
        $this->assertSoftDeleted('tasks', ['id' => $task->id]);

        $this->post("/tasks/{$task->id}/restore")
            ->assertRedirect('/tasks/archived');

        $this->assertNotSoftDeleted('tasks', ['id' => $task->id]);
    }

    public function test_force_destroy_removes_row_definitively(): void
    {
        $task = Task::create(['title' => 'Adiós', 'status' => 'todo']);
        $task->delete();

        $this->delete("/tasks/{$task->id}/force")
            ->assertRedirect('/tasks/archived');

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_force_destroy_rejects_not_archived_task(): void
    {
        $task = Task::create(['title' => 'Aún viva', 'status' => 'todo']);

        $this->delete("/tasks/{$task->id}/force")->assertNotFound();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'deleted_at' => null]);
    }

    public function test_bulk_restore_brings_back_several_tasks(): void
    {
        $a = Task::create(['title' => 'A', 'status' => 'todo']);
        $b = Task::create(['title' => 'B', 'status' => 'todo']);
        $c = Task::create(['title' => 'C', 'status' => 'todo']);
        $a->delete();
        $b->delete();
        $c->delete();

        $this->post('/tasks/bulk-restore', ['ids' => [$a->id, $b->id]])
            ->assertRedirect('/tasks/archived');

        $this->assertNotSoftDeleted('tasks', ['id' => $a->id]);
        $this->assertNotSoftDeleted('tasks', ['id' => $b->id]);
        $this->assertSoftDeleted('tasks', ['id' => $c->id]);   // no seleccionada
    }

    public function test_bulk_force_destroy_removes_several_tasks(): void
    {
        $a = Task::create(['title' => 'A', 'status' => 'todo']);
        $b = Task::create(['title' => 'B', 'status' => 'todo']);
        $a->delete();
        $b->delete();

        $this->delete('/tasks/bulk-force', ['ids' => [$a->id, $b->id]])
            ->assertRedirect('/tasks/archived');

        $this->assertDatabaseMissing('tasks', ['id' => $a->id]);
        $this->assertDatabaseMissing('tasks', ['id' => $b->id]);
    }

    public function test_bulk_force_destroy_ignores_non_archived_tasks(): void
    {
        $archived = Task::create(['title' => 'Archivada', 'status' => 'todo']);
        $archived->delete();
        $alive = Task::create(['title' => 'Viva', 'status' => 'todo']);

        $this->delete('/tasks/bulk-force', ['ids' => [$archived->id, $alive->id]])
            ->assertRedirect('/tasks/archived');

        $this->assertDatabaseMissing('tasks', ['id' => $archived->id]);
        $this->assertDatabaseHas('tasks', ['id' => $alive->id, 'deleted_at' => null]);
    }

    public function test_bulk_endpoints_require_ids(): void
    {
        $this->post('/tasks/bulk-restore', ['ids' => []])->assertSessionHasErrors('ids');
        $this->delete('/tasks/bulk-force', [])->assertSessionHasErrors('ids');
    }

    public function test_archived_page_renders_when_empty(): void
    {
        $this->get('/tasks/archived')->assertOk()->assertSee('No has archivado nada');
    }

    public function test_board_renders_the_six_fixed_columns(): void
    {
        $res = $this->get('/tasks')->assertOk();
        foreach (['Blocked', 'Backlog', 'To Do', 'Doing', 'Stand By', 'Done'] as $label) {
            $res->assertSee($label);
        }
    }

    public function test_store_accepts_new_blocked_and_standby_states(): void
    {
        $this->post('/tasks', ['title' => 'Bloqueada', 'status' => 'blocked'])->assertRedirect();
        $this->post('/tasks', ['title' => 'Pausada',   'status' => 'standby'])->assertRedirect();

        $this->assertSame(TaskStatus::Blocked, Task::where('title', 'Bloqueada')->firstOrFail()->status);
        $this->assertSame(TaskStatus::StandBy, Task::where('title', 'Pausada')->firstOrFail()->status);
    }

    public function test_move_to_blocked_or_standby_works(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);

        $this->patch("/tasks/{$task->id}/move", ['status' => 'blocked', 'position' => 0])
            ->assertOk()->assertJson(['ok' => true]);
        $this->assertSame(TaskStatus::Blocked, $task->fresh()->status);

        $this->patch("/tasks/{$task->id}/move", ['status' => 'standby', 'position' => 0])->assertOk();
        $this->assertSame(TaskStatus::StandBy, $task->fresh()->status);
    }
}

