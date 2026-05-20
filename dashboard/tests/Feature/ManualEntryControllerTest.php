<?php

namespace Tests\Feature;

use App\Enums\EntryKind;
use App\Models\ManualEntry;
use App\Models\Project;
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
}
