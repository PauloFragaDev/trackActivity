<?php

namespace Tests\Feature;

use App\Models\ActiveTimer;
use App\Models\ManualEntry;
use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimerTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_creates_an_active_timer(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);

        $this->post('/timer/start', ['task_id' => $task->id])
            ->assertOk()
            ->assertJson(['task_id' => $task->id, 'task_title' => 'T']);

        $this->assertSame(1, ActiveTimer::count());
        $this->assertSame($task->id, ActiveTimer::firstOrFail()->task_id);
    }

    public function test_start_requires_task_id_to_exist(): void
    {
        $this->post('/timer/start', ['task_id' => 999_999])
            ->assertSessionHasErrors('task_id');
    }

    public function test_starting_a_new_timer_closes_the_previous(): void
    {
        $project = Project::create(['code' => 'P', 'name' => 'P', 'color' => '#000']);
        $taskA   = Task::create(['title' => 'A', 'status' => 'todo', 'project_id' => $project->id]);
        $taskB   = Task::create(['title' => 'B', 'status' => 'todo']);

        // Previo: timer en A, arrancado hace 5 min.
        ActiveTimer::create([
            'task_id'   => $taskA->id,
            'starts_at' => CarbonImmutable::now('UTC')->subMinutes(5),
        ]);

        // Arrancamos uno nuevo en B → el de A se cierra y crea manual_entry.
        $this->post('/timer/start', ['task_id' => $taskB->id])->assertOk();

        $this->assertSame(1, ActiveTimer::count());
        $this->assertSame($taskB->id, ActiveTimer::firstOrFail()->task_id);

        // El timer anterior dejó una manual_entry vinculada a A.
        $entry = ManualEntry::firstOrFail();
        $this->assertSame($taskA->id, $entry->task_id);
        $this->assertSame($project->id, $entry->project_id);
        $this->assertGreaterThanOrEqual(5, $entry->durationMinutes());
    }

    public function test_stop_closes_active_timer_and_creates_manual_entry(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        ActiveTimer::create([
            'task_id'   => $task->id,
            'starts_at' => CarbonImmutable::now('UTC')->subMinutes(25),
        ]);

        $this->post('/timer/stop')
            ->assertOk()
            ->assertJsonStructure(['running', 'minutes_logged', 'manual_entry_id']);

        $this->assertSame(0, ActiveTimer::count());
        $entry = ManualEntry::firstOrFail();
        $this->assertSame($task->id, $entry->task_id);
        $this->assertGreaterThanOrEqual(25, $entry->durationMinutes());
    }

    public function test_stop_without_active_timer_is_a_noop(): void
    {
        $this->post('/timer/stop')->assertOk()->assertJson(['running' => false]);
        $this->assertSame(0, ManualEntry::count());
    }

    public function test_stop_under_one_minute_does_not_create_entry(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        ActiveTimer::create([
            'task_id'   => $task->id,
            'starts_at' => CarbonImmutable::now('UTC')->subSeconds(30),
        ]);

        $this->post('/timer/stop')->assertOk();

        $this->assertSame(0, ActiveTimer::count());
        $this->assertSame(0, ManualEntry::count(), 'misclick: <1 min se descarta');
    }

    public function test_deleting_a_task_clears_its_timer_reference(): void
    {
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        ActiveTimer::create([
            'task_id'   => $task->id,
            'starts_at' => CarbonImmutable::now('UTC'),
        ]);

        $task->forceDelete();

        // nullOnDelete deja el timer con task_id null.
        $this->assertSame(1, ActiveTimer::count());
        $this->assertNull(ActiveTimer::firstOrFail()->task_id);
    }
}
