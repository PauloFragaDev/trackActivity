<?php

namespace Tests\Feature;

use App\Enums\BlockStatus;
use App\Enums\EntryKind;
use App\Models\ActivityEvent;
use App\Models\ManualEntry;
use App\Models\Project;
use App\Models\ProjectMapping;
use App\Models\ScoringRule;
use App\Models\TimeBlock;
use App\Models\TimeBlockEvidence;
use App\Services\Aggregator;
use App\Services\Scoring\MappingResolver;
use App\Services\Scoring\Scorer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aggregator: agrupa activity_events en time_blocks de 15 min, los puntúa
 * y persiste bloque + evidencia. Idempotente y no destructivo con bloques
 * editados a mano.
 */
class AggregatorTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCK_START = '2026-05-19 10:00:00';
    private const BLOCK_END   = '2026-05-19 10:15:00';

    private function aggregator(): Aggregator
    {
        return new Aggregator(new Scorer(new MappingResolver()), 15);
    }

    private function range(): array
    {
        return [
            CarbonImmutable::parse(self::BLOCK_START),
            CarbonImmutable::parse(self::BLOCK_END),
        ];
    }

    private function project(): Project
    {
        $p = Project::create(['code' => 'TRACK', 'name' => 'TRACK']);
        ScoringRule::create([
            'signal_kind' => ScoringRule::KIND_VSCODE_IN_REPO,
            'weight'      => 5,
            'enabled'     => true,
        ]);
        ProjectMapping::create([
            'project_id'   => $p->id,
            'type'         => 'repository',
            'pattern'      => 'trackActivity',
            'is_regex'     => false,
            'weight_bonus' => 0,
            'enabled'      => true,
        ]);
        return $p;
    }

    private function codeEvent(string $at = '2026-05-19 10:05:00'): void
    {
        ActivityEvent::create([
            'occurred_at' => $at,
            'source'      => ActivityEvent::SOURCE_WINDOW,
            'app'         => 'code',
            'repo_name'   => 'trackActivity',
        ]);
    }

    public function test_rebuild_creates_a_scored_block_with_evidence(): void
    {
        $p = $this->project();
        $this->codeEvent();

        $count = $this->aggregator()->rebuildRange(...$this->range());

        $this->assertSame(1, $count);
        $block = TimeBlock::firstWhere('starts_at', self::BLOCK_START);
        $this->assertNotNull($block);
        $this->assertSame(BlockStatus::Auto, $block->status);
        $this->assertSame($p->id, $block->dominant_project_id);
        $this->assertSame(1, TimeBlockEvidence::where('time_block_id', $block->id)->count());
    }

    public function test_rebuild_is_idempotent(): void
    {
        $this->project();
        $this->codeEvent();
        $agg = $this->aggregator();

        $agg->rebuildRange(...$this->range());
        $agg->rebuildRange(...$this->range());

        // No se duplican ni el bloque ni la evidencia.
        $this->assertSame(1, TimeBlock::count());
        $this->assertSame(1, TimeBlockEvidence::count());
    }

    public function test_rebuild_skips_manually_edited_blocks(): void
    {
        $this->project();
        $this->codeEvent();
        // Bloque ya editado a mano, sin proyecto.
        TimeBlock::create([
            'starts_at'           => self::BLOCK_START,
            'ends_at'             => self::BLOCK_END,
            'dominant_project_id' => null,
            'confidence'          => 1.0,
            'status'              => BlockStatus::Edited,
            'generated_at'        => now('UTC'),
        ]);

        $this->aggregator()->rebuildRange(...$this->range());

        $block = TimeBlock::firstWhere('starts_at', self::BLOCK_START);
        $this->assertSame(BlockStatus::Edited, $block->status);
        $this->assertNull($block->dominant_project_id);
    }

    public function test_force_edited_overwrites_an_edited_block(): void
    {
        $p = $this->project();
        $this->codeEvent();
        TimeBlock::create([
            'starts_at'           => self::BLOCK_START,
            'ends_at'             => self::BLOCK_END,
            'dominant_project_id' => null,
            'confidence'          => 1.0,
            'status'              => BlockStatus::Edited,
            'generated_at'        => now('UTC'),
        ]);

        [$start, $end] = $this->range();
        $this->aggregator()->rebuildRange($start, $end, forceEdited: true);

        $block = TimeBlock::firstWhere('starts_at', self::BLOCK_START);
        $this->assertSame(BlockStatus::Auto, $block->status);
        $this->assertSame($p->id, $block->dominant_project_id);
    }

    public function test_block_with_only_idle_events_is_marked_idle(): void
    {
        $this->project();
        ActivityEvent::create([
            'occurred_at' => '2026-05-19 10:05:00',
            'source'      => ActivityEvent::SOURCE_IDLE,
            'metadata'    => ['state' => 'enter'],
        ]);

        $this->aggregator()->rebuildRange(...$this->range());

        $block = TimeBlock::firstWhere('starts_at', self::BLOCK_START);
        $this->assertSame(BlockStatus::Idle, $block->status);
        $this->assertNull($block->dominant_project_id);
    }

    public function test_range_without_events_creates_no_block(): void
    {
        $this->project();

        $count = $this->aggregator()->rebuildRange(...$this->range());

        $this->assertSame(0, $count);
        $this->assertSame(0, TimeBlock::count());
    }

    public function test_rebuild_day_scores_events_of_that_local_day(): void
    {
        $p = $this->project();
        $this->codeEvent('2026-05-19 10:05:00');

        $count = $this->aggregator()->rebuildDay(
            CarbonImmutable::parse('2026-05-19'),
            'UTC',
        );

        $this->assertGreaterThanOrEqual(1, $count);
        $block = TimeBlock::firstWhere('starts_at', self::BLOCK_START);
        $this->assertNotNull($block);
        $this->assertSame($p->id, $block->dominant_project_id);
    }

    public function test_rebuild_skips_a_slot_covered_by_a_manual_entry(): void
    {
        $this->project();
        $this->codeEvent();   // evento dentro del tramo 10:00–10:15
        ManualEntry::create([
            'starts_at' => self::BLOCK_START,
            'ends_at'   => self::BLOCK_END,
            'kind'      => EntryKind::Meeting,
            'title'     => 'Reunión',
        ]);

        $count = $this->aggregator()->rebuildRange(...$this->range());

        // La entrada manual manda: no se genera bloque automático.
        $this->assertSame(0, $count);
        $this->assertSame(0, TimeBlock::count());
    }
}
