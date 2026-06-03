<?php

namespace Tests\Feature;

use App\Enums\BlockStatus;
use App\Models\Project;
use App\Models\TimeBlock;
use App\Services\InsightsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    private function project(string $code): Project
    {
        return Project::create(['code' => $code, 'name' => $code]);
    }

    private function block(string $start, ?int $projectId, BlockStatus $status): void
    {
        TimeBlock::create([
            'starts_at'           => $start,
            'ends_at'             => CarbonImmutable::parse($start)->addMinutes(15),
            'dominant_project_id' => $projectId,
            'confidence'          => 0.8,
            'status'              => $status,
            'generated_at'        => now('UTC'),
        ]);
    }

    private function scenario(): array
    {
        $a = $this->project('GDR');
        $b = $this->project('DAY');
        // Secuencia (UTC): A A A | idle | B A   → no-idle comprimida: A,A,A,B,A
        $this->block('2026-05-19 10:00:00', $a->id, BlockStatus::Auto);
        $this->block('2026-05-19 10:15:00', $a->id, BlockStatus::Auto);
        $this->block('2026-05-19 10:30:00', $a->id, BlockStatus::Auto);
        $this->block('2026-05-19 10:45:00', null,   BlockStatus::Idle);
        $this->block('2026-05-19 11:00:00', $b->id, BlockStatus::Auto);
        $this->block('2026-05-19 11:15:00', $a->id, BlockStatus::Auto);

        return [$a, $b];
    }

    public function test_for_day_computes_focus_metrics(): void
    {
        $this->scenario();

        $m = app(InsightsService::class)->forDay(CarbonImmutable::parse('2026-05-19', 'UTC'));

        $this->assertSame(75, $m['active_minutes']);          // 5 bloques no-idle × 15
        $this->assertSame(15, $m['idle_minutes']);            // 1 bloque idle
        $this->assertSame(2, $m['context_switches']);         // A→B y B→A
        $this->assertSame(45, $m['longest_focus_minutes']);   // los 3 A iniciales
        $this->assertSame(45, $m['deep_work_minutes']);       // solo ese tramo ≥ 25
        $this->assertSame(60, $m['deep_work_pct']);           // 45 / 75
    }

    public function test_for_day_breakdown_and_narrative(): void
    {
        $this->scenario();

        $m = app(InsightsService::class)->forDay(CarbonImmutable::parse('2026-05-19', 'UTC'));

        $this->assertSame('GDR', $m['by_project'][0]['project_name']);  // 60 min, el top
        $this->assertStringContainsString('Hoy: sobre todo GDR', $m['narrative']);
        $this->assertStringContainsString('2 cambios de contexto', $m['narrative']);
    }

    public function test_empty_day_has_zero_metrics_and_idle_narrative(): void
    {
        $m = app(InsightsService::class)->forDay(CarbonImmutable::parse('2026-05-20', 'UTC'));

        $this->assertSame(0, $m['active_minutes']);
        $this->assertSame('Sin actividad registrada hoy.', $m['narrative']);
    }

    public function test_project_trend_returns_labels_and_series(): void
    {
        $this->scenario();

        $trend = app(InsightsService::class)->projectTrend(8);

        $this->assertCount(8, $trend['labels']);
        $this->assertIsArray($trend['series']);
        foreach ($trend['series'] as $s) {
            $this->assertCount(8, $s['data']);   // un valor por semana
        }
    }
}
