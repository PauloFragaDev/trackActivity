<?php

namespace Tests\Feature;

use App\Enums\EntryKind;
use App\Models\ManualEntry;
use App\Models\Project;
use App\Services\Export\Exporter;
use App\Services\Export\ExportQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Las entradas manuales (reuniones, correcciones) deben aparecer en el
 * export junto a las sesiones automáticas.
 */
class ExporterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tracker.display_timezone' => 'UTC']);
    }

    private function query(array $overrides = []): ExportQuery
    {
        $from = CarbonImmutable::parse('2026-05-22', 'UTC')->startOfDay();

        return new ExportQuery(
            fromLocal: $from,
            toLocal: $from->addDay(),
            projectCodes: $overrides['projectCodes'] ?? [],
            groupBy: $overrides['groupBy'] ?? ExportQuery::GROUP_SESSION,
            format: $overrides['format'] ?? ExportQuery::FORMAT_TXT,
        );
    }

    private function entry(array $overrides = []): ManualEntry
    {
        return ManualEntry::create(array_merge([
            'starts_at' => '2026-05-22 10:00:00',
            'ends_at'   => '2026-05-22 11:00:00',
            'kind'      => EntryKind::Meeting,
            'title'     => 'Reunión de planning',
        ], $overrides));
    }

    public function test_manual_entry_appears_in_the_report_and_totals(): void
    {
        $this->entry();

        $report = app(Exporter::class)->buildReport($this->query());

        $this->assertArrayHasKey('2026-05-22', $report->days);
        $sessions = $report->days['2026-05-22']['sessions'];
        $this->assertCount(1, $sessions);
        $this->assertSame('Reunión de planning', $sessions[0]['summary']);
        $this->assertSame(60, $report->totalMinutes);
    }

    public function test_manual_entry_notes_are_included_in_the_summary(): void
    {
        $this->entry(['notes' => 'Con el equipo de backend']);

        $report = app(Exporter::class)->buildReport($this->query());

        $this->assertSame(
            'Reunión de planning — Con el equipo de backend',
            $report->days['2026-05-22']['sessions'][0]['summary'],
        );
    }

    public function test_manual_entry_renders_in_txt_marked_as_manual(): void
    {
        $this->entry();
        $exporter = app(Exporter::class);

        $txt = $exporter->render($exporter->buildReport($this->query()));

        $this->assertStringContainsString('Reunión de planning', $txt);
        $this->assertStringContainsString('manual', $txt);
    }

    public function test_manual_entry_respects_the_project_filter(): void
    {
        $ywl = Project::create(['code' => 'YWL', 'name' => 'YWL']);
        $this->entry(['project_id' => $ywl->id]);

        // Filtrando por otro proyecto, la entrada no aparece.
        $excluded = app(Exporter::class)->buildReport($this->query(['projectCodes' => ['JASPER']]));
        $this->assertEmpty($excluded->days);

        // Filtrando por su proyecto, sí.
        $included = app(Exporter::class)->buildReport($this->query(['projectCodes' => ['YWL']]));
        $this->assertArrayHasKey('2026-05-22', $included->days);
    }

    public function test_manual_entry_counts_in_project_day_grouping(): void
    {
        $ywl = Project::create(['code' => 'YWL', 'name' => 'YWL']);
        $this->entry(['project_id' => $ywl->id]);

        $report = app(Exporter::class)->buildReport($this->query(['groupBy' => ExportQuery::GROUP_PROJECT_DAY]));

        $row = $report->days['2026-05-22']['sessions'][0];
        $this->assertSame('YWL', $row['project_code']);
        $this->assertSame(60, $row['minutes']);
        $this->assertSame(60, $report->totalMinutes);
    }
}
