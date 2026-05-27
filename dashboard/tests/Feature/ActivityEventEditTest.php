<?php

namespace Tests\Feature;

use App\Models\ActivityEvent;
use App\Models\Project;
use App\Models\TimeBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityEventEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_project_attributes_the_block(): void
    {
        $project = Project::create(['code' => 'TST', 'name' => 'Test', 'color' => '#10b981']);
        $event = ActivityEvent::create([
            'occurred_at' => '2026-05-27 10:07:00',
            'source'      => 'window',
            'app'         => 'ghostty',
            'title'       => 'Dev Terminal',
        ]);

        $this->patch("/activity-events/{$event->id}", ['project_id' => $project->id])
            ->assertOk()
            ->assertJson(['project_id' => $project->id, 'project_code' => 'TST']);

        $this->assertSame($project->id, $event->fresh()->project_id);

        // El bloque 10:00-10:15 debe existir y estar atribuido a TST.
        $block = TimeBlock::query()
            ->where('starts_at', '2026-05-27 10:00:00')
            ->first();
        $this->assertNotNull($block, 'El bloque del tramo del evento se debería haber generado.');
        $this->assertSame($project->id, $block->dominant_project_id);
    }

    public function test_clearing_project_reverts_to_auto(): void
    {
        $project = Project::create(['code' => 'TST', 'name' => 'Test', 'color' => '#10b981']);
        $event = ActivityEvent::create([
            'occurred_at' => '2026-05-27 11:07:00',
            'source'      => 'window',
            'app'         => 'ghostty',
            'title'       => 'Dev Terminal',
            'project_id'  => $project->id,
        ]);

        $this->patch("/activity-events/{$event->id}", ['project_id' => null])
            ->assertOk();

        $this->assertNull($event->fresh()->project_id);
    }

    public function test_validates_project_must_exist(): void
    {
        $event = ActivityEvent::create([
            'occurred_at' => '2026-05-27 12:00:00',
            'source'      => 'window',
        ]);

        $this->patch("/activity-events/{$event->id}", ['project_id' => 999999])
            ->assertSessionHasErrors('project_id');
    }
}
