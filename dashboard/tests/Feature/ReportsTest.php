<?php

namespace Tests\Feature;

use App\Models\ManualEntry;
use App\Models\Project;
use App\Models\TimeBlock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_loads_for_each_period(): void
    {
        foreach (['week', 'month', '30d'] as $p) {
            $this->get('/reports?period=' . $p)
                ->assertOk()
                ->assertSee('Informes');
        }
    }

    public function test_reports_aggregates_time_blocks_by_project(): void
    {
        $project = Project::create(['code' => 'X', 'name' => 'Proyecto X', 'color' => '#3B82F6']);

        // Un bloque de 15 min hoy (UTC stored).
        $today = CarbonImmutable::now('UTC')->startOfHour();
        TimeBlock::create([
            'starts_at'           => $today,
            'ends_at'             => $today->addMinutes(15),
            'status'              => 'auto',
            'dominant_project_id' => $project->id,
            'confidence'          => 1.0,
            'generated_at'        => $today,
            'scoring_snapshot'    => '{}',
        ]);

        $response = $this->get('/reports?period=week')->assertOk();
        $response->assertSeeText('X');   // chip con el code del proyecto
        $response->assertViewHas('byProject', function ($rows) use ($project) {
            return collect($rows)->contains(function ($r) use ($project) {
                return $r['project_id'] === $project->id && $r['minutes'] === 15;
            });
        });
    }

    public function test_reports_aggregates_manual_entries(): void
    {
        $project = Project::create(['code' => 'M', 'name' => 'Manual', 'color' => '#84CC16']);

        // Reunión de 30 min hoy.
        $today = CarbonImmutable::now('UTC')->startOfHour();
        ManualEntry::create([
            'starts_at'  => $today,
            'ends_at'    => $today->addMinutes(30),
            'project_id' => $project->id,
            'kind'       => 'meeting',
            'title'      => 'Standup',
        ]);

        $this->get('/reports?period=week')
            ->assertOk()
            ->assertViewHas('totalMinutes', 30)
            ->assertViewHas('byProject', function ($rows) use ($project) {
                return collect($rows)->firstWhere('project_id', $project->id)['minutes'] === 30;
            });
    }

    public function test_reports_groups_time_blocks_and_manual_entries_under_same_project(): void
    {
        $project = Project::create(['code' => 'Z', 'name' => 'Z', 'color' => '#000000']);

        $today = CarbonImmutable::now('UTC')->startOfHour();
        TimeBlock::create([
            'starts_at' => $today, 'ends_at' => $today->addMinutes(15),
            'status' => 'auto', 'dominant_project_id' => $project->id,
            'confidence' => 1.0, 'generated_at' => $today, 'scoring_snapshot' => '{}',
        ]);
        ManualEntry::create([
            'starts_at' => $today->addMinutes(30), 'ends_at' => $today->addMinutes(60),
            'project_id' => $project->id, 'kind' => 'focus', 'title' => 'Foco',
        ]);

        $this->get('/reports?period=week')
            ->assertViewHas('totalMinutes', 45);   // 15 + 30
    }

    public function test_by_day_fills_zeros_for_inactive_days(): void
    {
        // Sin datos, todos los días en 0.
        $this->get('/reports?period=week')
            ->assertViewHas('byDay', function ($rows) {
                return count($rows) === 7
                    && collect($rows)->every(fn ($r) => $r['minutes'] === 0);
            })
            ->assertViewHas('daysActive', 0);
    }

    public function test_period_defaults_to_week(): void
    {
        $this->get('/reports')
            ->assertOk()
            ->assertViewHas('period', 'week')
            ->assertViewHas('byDay', fn ($rows) => count($rows) === 7);
    }

    public function test_30d_period_returns_30_days(): void
    {
        $this->get('/reports?period=30d')
            ->assertOk()
            ->assertViewHas('byDay', fn ($rows) => count($rows) === 30);
    }
}
