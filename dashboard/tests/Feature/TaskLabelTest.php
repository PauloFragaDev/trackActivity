<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_palette_page_loads(): void
    {
        $this->get('/task-labels')->assertOk()->assertSee('Etiquetas');
    }

    public function test_label_creation_validates_color_format(): void
    {
        $this->post('/task-labels', ['title' => 'Urgente', 'color' => 'red'])
            ->assertSessionHasErrors('color');
        $this->assertSame(0, TaskLabel::count());
    }

    public function test_label_can_be_created(): void
    {
        $this->post('/task-labels', ['title' => 'Urgente', 'color' => '#DC2626'])
            ->assertRedirect();

        $label = TaskLabel::firstOrFail();
        $this->assertSame('Urgente', $label->title);
        $this->assertSame('#DC2626', $label->color);
    }

    public function test_a_task_can_be_assigned_labels(): void
    {
        $a = TaskLabel::create(['title' => 'frontend', 'color' => '#3B82F6']);
        $b = TaskLabel::create(['title' => 'urgente',  'color' => '#DC2626']);

        $this->post('/tasks', [
            'title'     => 'Mi tarea',
            'status'    => 'todo',
            'label_ids' => [$a->id, $b->id],
        ])->assertRedirect();

        $task = Task::firstOrFail();
        $this->assertSame([$a->id, $b->id], $task->labels()->pluck('task_labels.id')->sort()->values()->all());
    }

    public function test_updating_a_task_syncs_labels(): void
    {
        $a = TaskLabel::create(['title' => 'a', 'color' => '#3B82F6']);
        $b = TaskLabel::create(['title' => 'b', 'color' => '#DC2626']);
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        $task->labels()->sync([$a->id]);

        $this->patch("/tasks/{$task->id}", [
            'title'     => 'T',
            'status'    => 'todo',
            'label_ids' => [$b->id],
        ])->assertRedirect();

        $this->assertSame([$b->id], $task->labels()->pluck('task_labels.id')->all());
    }

    public function test_deleting_a_label_unassigns_it_from_tasks(): void
    {
        $label = TaskLabel::create(['title' => 'temp', 'color' => '#84CC16']);
        $task = Task::create(['title' => 'T', 'status' => 'todo']);
        $task->labels()->sync([$label->id]);

        $this->delete("/task-labels/{$label->id}")->assertRedirect();

        $this->assertSame(0, $task->labels()->count());
        $this->assertSame(0, TaskLabel::count());
    }
}
