<?php

namespace Tests\Feature;

use App\Enums\BlockStatus;
use App\Enums\EntryKind;
use App\Models\ManualEntry;
use App\Models\Project;
use App\Models\TimeBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CRUD de entradas manuales (reuniones, correcciones de horas) vía
 * /manual-entries. El formulario trabaja en hora local; la BBDD en UTC.
 */
class ManualEntryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Por defecto sin desfase: hora local == UTC.
        config(['tracker.display_timezone' => 'UTC']);
    }

    /** @param array<string,mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'date'       => '2026-05-22',
            'start_time' => '10:00',
            'end_time'   => '11:00',
            'kind'       => 'meeting',
            'title'      => 'Reunión de planning',
            'return'     => 'day',
        ], $overrides);
    }

    private function entry(array $overrides = []): ManualEntry
    {
        return ManualEntry::create(array_merge([
            'starts_at' => '2026-05-22 10:00:00',
            'ends_at'   => '2026-05-22 11:00:00',
            'kind'      => EntryKind::Meeting,
            'title'     => 'Original',
        ], $overrides));
    }

    public function test_store_creates_a_manual_entry_and_redirects_to_day(): void
    {
        $project = Project::create(['code' => 'TRACK', 'name' => 'TRACK']);

        $this->post('/manual-entries', $this->payload(['project_id' => $project->id]))
            ->assertRedirect(route('timeline.day', ['date' => '2026-05-22']));

        $entry = ManualEntry::firstOrFail();
        $this->assertSame('Reunión de planning', $entry->title);
        $this->assertSame($project->id, $entry->project_id);
        $this->assertSame(EntryKind::Meeting, $entry->kind);
        $this->assertSame('2026-05-22 10:00:00', $entry->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-22 11:00:00', $entry->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_store_converts_local_time_to_utc(): void
    {
        // America/Bogota: UTC-5 fijo (sin horario de verano).
        config(['tracker.display_timezone' => 'America/Bogota']);

        $this->post('/manual-entries', $this->payload(['start_time' => '10:00', 'end_time' => '11:30']));

        $entry = ManualEntry::firstOrFail();
        $this->assertSame('2026-05-22 15:00:00', $entry->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-22 16:30:00', $entry->ends_at->format('Y-m-d H:i:s'));
    }

    public function test_store_redirects_to_calendar_when_requested(): void
    {
        $this->post('/manual-entries', $this->payload(['return' => 'calendar']))
            ->assertRedirect(route('calendar.month', ['ym' => '2026-05']));
    }

    public function test_store_allows_no_project(): void
    {
        $this->post('/manual-entries', $this->payload())->assertRedirect();

        $this->assertNull(ManualEntry::firstOrFail()->project_id);
    }

    public function test_update_modifies_the_entry(): void
    {
        $entry   = $this->entry();
        $project = Project::create(['code' => 'YWL', 'name' => 'YWL']);

        $this->patch("/manual-entries/{$entry->id}", $this->payload([
            'start_time' => '14:00',
            'end_time'   => '15:15',
            'kind'       => 'focus',
            'title'      => 'Bloque de trabajo',
            'project_id' => $project->id,
        ]))->assertRedirect(route('timeline.day', ['date' => '2026-05-22']));

        $entry->refresh();
        $this->assertSame('Bloque de trabajo', $entry->title);
        $this->assertSame(EntryKind::Focus, $entry->kind);
        $this->assertSame($project->id, $entry->project_id);
        $this->assertSame('2026-05-22 14:00:00', $entry->starts_at->format('Y-m-d H:i:s'));
        $this->assertSame(75, $entry->durationMinutes());
    }

    public function test_destroy_deletes_the_entry(): void
    {
        $entry = $this->entry();

        $this->delete("/manual-entries/{$entry->id}", ['date' => '2026-05-22', 'return' => 'day'])
            ->assertRedirect(route('timeline.day', ['date' => '2026-05-22']));

        $this->assertDatabaseMissing('manual_entries', ['id' => $entry->id]);
    }

    public function test_store_rejects_end_before_or_equal_start(): void
    {
        $this->post('/manual-entries', $this->payload(['start_time' => '11:00', 'end_time' => '10:00']))
            ->assertSessionHasErrors('end_time');

        $this->assertSame(0, ManualEntry::count());
    }

    public function test_store_rejects_missing_title(): void
    {
        $this->post('/manual-entries', $this->payload(['title' => '']))
            ->assertSessionHasErrors('title');
    }

    public function test_store_rejects_invalid_kind(): void
    {
        $this->post('/manual-entries', $this->payload(['kind' => 'lunch']))
            ->assertSessionHasErrors('kind');
    }

    public function test_store_rejects_nonexistent_project(): void
    {
        $this->post('/manual-entries', $this->payload(['project_id' => 999999]))
            ->assertSessionHasErrors('project_id');
    }

    public function test_store_rejects_bad_time_format(): void
    {
        $this->post('/manual-entries', $this->payload(['start_time' => '25:99']))
            ->assertSessionHasErrors('start_time');
    }

    public function test_duration_minutes_accessor(): void
    {
        $entry = $this->entry([
            'starts_at' => '2026-05-22 09:00:00',
            'ends_at'   => '2026-05-22 10:45:00',
        ]);

        $this->assertSame(105, $entry->durationMinutes());
    }

    // ─────────────────── Solapamientos ───────────────────

    private function block(string $start, string $end, BlockStatus $status = BlockStatus::Auto): TimeBlock
    {
        return TimeBlock::create([
            'starts_at'    => $start,
            'ends_at'      => $end,
            'confidence'   => 0.9,
            'status'       => $status,
            'generated_at' => now('UTC'),
        ]);
    }

    public function test_store_overlapping_a_manual_entry_asks_for_confirmation(): void
    {
        $this->entry();   // 2026-05-22 10:00–11:00

        $this->post('/manual-entries', $this->payload(['start_time' => '10:30', 'end_time' => '11:30']))
            ->assertSessionHas('overlap');

        // La nueva no se crea: sigue habiendo solo la original.
        $this->assertSame(1, ManualEntry::count());
    }

    public function test_confirm_replace_deletes_the_overlapping_manual_entry(): void
    {
        $old = $this->entry();   // título 'Original'

        $this->post('/manual-entries', $this->payload([
            'start_time'      => '10:30',
            'end_time'        => '11:30',
            'title'           => 'Reunión nueva',
            'confirm_replace' => '1',
        ]))->assertRedirect();

        $this->assertDatabaseMissing('manual_entries', ['id' => $old->id]);
        $this->assertSame(1, ManualEntry::count());
        $this->assertSame('Reunión nueva', ManualEntry::firstOrFail()->title);
    }

    public function test_store_overlapping_an_auto_block_asks_for_confirmation(): void
    {
        $this->block('2026-05-22 10:00:00', '2026-05-22 10:15:00');

        $this->post('/manual-entries', $this->payload(['start_time' => '10:00', 'end_time' => '11:00']))
            ->assertSessionHas('overlap');

        $this->assertSame(0, ManualEntry::count());
    }

    public function test_confirm_replace_deletes_overlapping_auto_blocks(): void
    {
        $this->block('2026-05-22 10:00:00', '2026-05-22 10:15:00');

        $this->post('/manual-entries', $this->payload([
            'start_time'      => '10:00',
            'end_time'        => '11:00',
            'confirm_replace' => '1',
        ]))->assertRedirect();

        $this->assertSame(0, TimeBlock::count());
        $this->assertSame(1, ManualEntry::count());
    }

    public function test_idle_blocks_do_not_count_as_overlap(): void
    {
        $this->block('2026-05-22 10:00:00', '2026-05-22 10:15:00', BlockStatus::Idle);

        $this->post('/manual-entries', $this->payload(['start_time' => '10:00', 'end_time' => '11:00']))
            ->assertSessionHas('status');

        // El bloque idle no es conflicto → la entrada se guarda directamente.
        $this->assertSame(1, ManualEntry::count());
    }

    public function test_update_does_not_flag_itself_as_overlap(): void
    {
        $entry = $this->entry();

        $this->patch("/manual-entries/{$entry->id}", $this->payload([
            'start_time' => '10:00',
            'end_time'   => '11:00',
            'title'      => 'Mismo horario, otro título',
        ]))->assertSessionHas('status');

        $this->assertSame('Mismo horario, otro título', $entry->fresh()->title);
    }

    public function test_non_overlapping_entry_saves_without_prompt(): void
    {
        $this->entry();   // 10:00–11:00

        $this->post('/manual-entries', $this->payload(['start_time' => '11:00', 'end_time' => '12:00']))
            ->assertSessionHas('status');

        $this->assertSame(2, ManualEntry::count());
    }
}
