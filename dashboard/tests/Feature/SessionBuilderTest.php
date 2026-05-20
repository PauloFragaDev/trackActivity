<?php

namespace Tests\Feature;

use App\Enums\BlockStatus;
use App\Models\Project;
use App\Models\TimeBlock;
use App\Services\SessionBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Agrupacion de time_blocks en sesiones. Lo clave de esta feature: el
 * `status` concreto NO rompe la sesion — solo proyecto + idle/no-idle.
 */
class SessionBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Sin desfase de zona: dia local == dia UTC.
        config(['tracker.display_timezone' => 'UTC']);
    }

    private function builder(): SessionBuilder
    {
        return new SessionBuilder(idleGapMinutes: 5);
    }

    private function block(string $start, ?int $projectId, BlockStatus $status, ?float $confidence = 0.8): TimeBlock
    {
        return TimeBlock::create([
            'starts_at'           => $start,
            'ends_at'             => CarbonImmutable::parse($start)->addMinutes(15),
            'dominant_project_id' => $projectId,
            'confidence'          => $confidence,
            'status'              => $status,
            'generated_at'        => now('UTC'),
        ]);
    }

    private function buildDay(): array
    {
        return $this->builder()->buildForDay(CarbonImmutable::parse('2026-05-19'));
    }

    public function test_contiguous_blocks_same_project_form_one_session(): void
    {
        $p = Project::create(['code' => 'TRACK', 'name' => 'TRACK']);
        $this->block('2026-05-19 10:00:00', $p->id, BlockStatus::Auto);
        $this->block('2026-05-19 10:15:00', $p->id, BlockStatus::Auto);

        $sessions = $this->buildDay();

        $this->assertCount(1, $sessions);
        $this->assertSame(2, $sessions[0]['block_count']);
        $this->assertSame(30, $sessions[0]['duration_minutes']);
    }

    public function test_edited_block_does_not_break_session_with_auto_neighbor(): void
    {
        $p = Project::create(['code' => 'TRACK', 'name' => 'TRACK']);
        $this->block('2026-05-19 10:00:00', $p->id, BlockStatus::Auto);
        $this->block('2026-05-19 10:15:00', $p->id, BlockStatus::Edited, 1.0);

        $sessions = $this->buildDay();

        // Un bloque editado se funde con su vecino auto del mismo proyecto.
        $this->assertCount(1, $sessions);
        // La sesion con algun bloque editado se reporta como 'edited'.
        $this->assertSame(BlockStatus::Edited->value, $sessions[0]['status']);
        $this->assertSame('editado', $sessions[0]['confidence_label']);
    }

    public function test_different_projects_split_into_separate_sessions(): void
    {
        $a = Project::create(['code' => 'TRACK', 'name' => 'TRACK']);
        $b = Project::create(['code' => 'JASPER', 'name' => 'JASPER']);
        $this->block('2026-05-19 10:00:00', $a->id, BlockStatus::Auto);
        $this->block('2026-05-19 10:15:00', $b->id, BlockStatus::Auto);

        $this->assertCount(2, $this->buildDay());
    }

    public function test_idle_block_between_active_blocks_is_its_own_session(): void
    {
        $p = Project::create(['code' => 'TRACK', 'name' => 'TRACK']);
        $this->block('2026-05-19 10:00:00', $p->id, BlockStatus::Auto);
        $this->block('2026-05-19 10:15:00', null, BlockStatus::Idle, null);
        $this->block('2026-05-19 10:30:00', $p->id, BlockStatus::Auto);

        $sessions = $this->buildDay();

        $this->assertCount(3, $sessions);
        $this->assertTrue($sessions[1]['is_idle']);
        $this->assertSame('idle', $sessions[1]['confidence_label']);
    }

    public function test_non_contiguous_blocks_split_even_with_same_project(): void
    {
        $p = Project::create(['code' => 'TRACK', 'name' => 'TRACK']);
        $this->block('2026-05-19 10:00:00', $p->id, BlockStatus::Auto);
        // Hueco temporal: el siguiente bloque empieza una hora despues.
        $this->block('2026-05-19 11:00:00', $p->id, BlockStatus::Auto);

        $this->assertCount(2, $this->buildDay());
    }

    public function test_block_ids_are_exposed_on_each_session(): void
    {
        $p = Project::create(['code' => 'TRACK', 'name' => 'TRACK']);
        $b1 = $this->block('2026-05-19 10:00:00', $p->id, BlockStatus::Auto);
        $b2 = $this->block('2026-05-19 10:15:00', $p->id, BlockStatus::Auto);

        $sessions = $this->buildDay();

        $this->assertSame([$b1->id, $b2->id], $sessions[0]['block_ids']);
    }

    public function test_empty_day_returns_no_sessions(): void
    {
        $this->assertSame([], $this->buildDay());
    }
}
