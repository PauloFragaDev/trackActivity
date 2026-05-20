<?php

namespace Tests\Feature;

use App\Enums\BlockStatus;
use App\Enums\SummaryEngine;
use App\Models\GeneratedSummary;
use App\Models\Project;
use App\Models\TimeBlock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Edicion manual de sesiones via PATCH /blocks y PATCH /blocks/reset.
 */
class TimeBlockControllerTest extends TestCase
{
    use RefreshDatabase;

    private function project(string $code): Project
    {
        return Project::create(['code' => $code, 'name' => $code]);
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

    public function test_update_reassigns_project_and_marks_blocks_edited(): void
    {
        $track  = $this->project('TRACK');
        $jasper = $this->project('JASPER');
        $b1 = $this->block('2026-05-19 10:00:00', $track->id, BlockStatus::Auto);
        $b2 = $this->block('2026-05-19 10:15:00', $track->id, BlockStatus::Auto);

        $this->patch('/blocks', [
            'block_ids'  => [$b1->id, $b2->id],
            'project_id' => $jasper->id,
            'date'       => '2026-05-19',
        ])->assertRedirect(route('timeline.day', ['date' => '2026-05-19']));

        foreach ([$b1, $b2] as $b) {
            $b->refresh();
            $this->assertSame($jasper->id, $b->dominant_project_id);
            $this->assertSame(BlockStatus::Edited, $b->status);
            $this->assertSame(1.0, $b->confidence);
        }
    }

    public function test_update_to_no_project_is_allowed(): void
    {
        $b = $this->block('2026-05-19 10:00:00', $this->project('TRACK')->id, BlockStatus::Auto);

        $this->patch('/blocks', [
            'block_ids'  => [$b->id],
            'project_id' => null,
            'date'       => '2026-05-19',
        ])->assertRedirect();

        $b->refresh();
        $this->assertNull($b->dominant_project_id);
        $this->assertSame(BlockStatus::Edited, $b->status);
    }

    public function test_update_overwrites_summary_and_preserves_original_engine(): void
    {
        $p = $this->project('TRACK');
        $b = $this->block('2026-05-19 10:00:00', $p->id, BlockStatus::Auto);
        GeneratedSummary::create([
            'time_block_id'  => $b->id,
            'text'           => 'Resumen automatico.',
            'engine'         => SummaryEngine::Template,
            'edited_by_user' => false,
            'generated_at'   => now('UTC'),
        ]);

        $this->patch('/blocks', [
            'block_ids'    => [$b->id],
            'project_id'   => $p->id,
            'summary_text' => 'Resumen corregido a mano.',
            'date'         => '2026-05-19',
        ])->assertRedirect();

        $summary = $b->summary()->first();
        $this->assertSame('Resumen corregido a mano.', $summary->text);
        $this->assertTrue($summary->edited_by_user);
        // El engine original (template) se conserva, no se pisa con 'manual'.
        $this->assertSame(SummaryEngine::Template, $summary->engine);
    }

    public function test_update_creates_manual_summary_when_none_exists(): void
    {
        $p = $this->project('TRACK');
        $b = $this->block('2026-05-19 10:00:00', $p->id, BlockStatus::Auto);

        $this->patch('/blocks', [
            'block_ids'    => [$b->id],
            'project_id'   => $p->id,
            'summary_text' => 'Resumen escrito a mano.',
            'date'         => '2026-05-19',
        ])->assertRedirect();

        $summary = $b->summary()->first();
        $this->assertNotNull($summary);
        $this->assertSame(SummaryEngine::Manual, $summary->engine);
        $this->assertTrue($summary->edited_by_user);
    }

    public function test_update_without_summary_text_keeps_existing_summary(): void
    {
        $p = $this->project('TRACK');
        $b = $this->block('2026-05-19 10:00:00', $p->id, BlockStatus::Auto);
        GeneratedSummary::create([
            'time_block_id'  => $b->id,
            'text'           => 'Resumen intacto.',
            'engine'         => SummaryEngine::Template,
            'edited_by_user' => false,
            'generated_at'   => now('UTC'),
        ]);

        $this->patch('/blocks', [
            'block_ids'  => [$b->id],
            'project_id' => $p->id,
            'date'       => '2026-05-19',
        ])->assertRedirect();

        $summary = $b->summary()->first();
        $this->assertSame('Resumen intacto.', $summary->text);
        $this->assertFalse($summary->edited_by_user);
    }

    public function test_idle_blocks_are_left_untouched(): void
    {
        $p    = $this->project('TRACK');
        $idle = $this->block('2026-05-19 10:00:00', null, BlockStatus::Idle, null);

        $this->patch('/blocks', [
            'block_ids'  => [$idle->id],
            'project_id' => $p->id,
            'date'       => '2026-05-19',
        ])->assertRedirect();

        $idle->refresh();
        $this->assertSame(BlockStatus::Idle, $idle->status);
        $this->assertNull($idle->dominant_project_id);
    }

    public function test_update_rejects_nonexistent_project(): void
    {
        $b = $this->block('2026-05-19 10:00:00', $this->project('TRACK')->id, BlockStatus::Auto);

        $this->patch('/blocks', [
            'block_ids'  => [$b->id],
            'project_id' => 999999,
            'date'       => '2026-05-19',
        ])->assertSessionHasErrors('project_id');

        $b->refresh();
        $this->assertSame(BlockStatus::Auto, $b->status);
    }

    public function test_update_rejects_empty_block_ids(): void
    {
        $this->patch('/blocks', [
            'block_ids' => [],
            'date'      => '2026-05-19',
        ])->assertSessionHasErrors('block_ids');
    }

    public function test_update_rejects_missing_date(): void
    {
        $b = $this->block('2026-05-19 10:00:00', $this->project('TRACK')->id, BlockStatus::Auto);

        $this->patch('/blocks', [
            'block_ids' => [$b->id],
        ])->assertSessionHasErrors('date');
    }

    public function test_reset_returns_blocks_to_auto_and_frees_summary(): void
    {
        $p = $this->project('TRACK');
        $b = $this->block('2026-05-19 10:00:00', $p->id, BlockStatus::Edited, 1.0);
        GeneratedSummary::create([
            'time_block_id'  => $b->id,
            'text'           => 'Resumen editado.',
            'engine'         => SummaryEngine::Template,
            'edited_by_user' => true,
            'generated_at'   => now('UTC'),
        ]);

        $this->patch('/blocks/reset', [
            'block_ids' => [$b->id],
            'date'      => '2026-05-19',
        ])->assertRedirect(route('timeline.day', ['date' => '2026-05-19']));

        $b->refresh();
        $this->assertSame(BlockStatus::Auto, $b->status);
        $this->assertFalse($b->summary()->first()->edited_by_user);
    }

    public function test_reset_skips_idle_blocks(): void
    {
        $idle = $this->block('2026-05-19 10:00:00', null, BlockStatus::Idle, null);

        $this->patch('/blocks/reset', [
            'block_ids' => [$idle->id],
            'date'      => '2026-05-19',
        ])->assertRedirect();

        $this->assertSame(BlockStatus::Idle, $idle->fresh()->status);
    }
}
