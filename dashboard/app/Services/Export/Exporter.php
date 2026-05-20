<?php

namespace App\Services\Export;

use App\Enums\BlockStatus;
use App\Models\ManualEntry;
use App\Models\Project;
use App\Services\Export\Renderers\CsvRenderer;
use App\Services\Export\Renderers\MarkdownRenderer;
use App\Services\Export\Renderers\Renderer;
use App\Services\Export\Renderers\TxtRenderer;
use App\Services\SessionBuilder;
use App\Services\Summaries\SummaryGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Orquestador del export. Construye un Report a partir de time_blocks via
 * SessionBuilder (ya filtra por proyecto y confianza) y lo renderiza.
 */
class Exporter
{
    public function __construct(
        private readonly SessionBuilder $sessions,
        private readonly SummaryGenerator $summaries,
    ) {}

    public function buildReport(ExportQuery $query): Report
    {
        $tz       = $query->fromLocal->getTimezone()->getName();
        $cursor   = $query->fromLocal->startOfDay();
        $until    = $query->toLocal->startOfDay();
        $minConf  = $query->minConfidenceValue();
        $codeSet  = array_flip($query->projectCodes);

        $days  = [];
        $totals = [];     // project_code => minutes  (null code = sin proyecto)
        $names  = [];     // project_code => project_name
        $grand  = 0;

        while ($cursor->lt($until)) {
            // Sesiones automáticas + entradas manuales del día, en orden.
            $items = collect($this->sessions->buildForDay($cursor))
                ->concat($this->manualEntriesForDay($cursor, $tz)
                    ->map(fn (ManualEntry $e) => $this->manualEntryAsSession($e, $tz)))
                ->sortBy(fn (array $s) => $s['starts_at_local'])
                ->values();

            $filtered = [];

            foreach ($items as $s) {
                // Filtro idle
                if ($s['status'] === BlockStatus::Idle->value && ! $query->includeIdle) {
                    continue;
                }
                // Filtro proyecto
                if (! empty($codeSet)) {
                    $code = $s['project']?->code;
                    if ($code === null || ! isset($codeSet[$code])) {
                        continue;
                    }
                }
                // Filtro confianza
                if ($s['confidence'] !== null && $s['confidence'] < $minConf) {
                    continue;
                }

                $filtered[] = $s;
            }

            if ($query->groupBy === ExportQuery::GROUP_PROJECT_DAY) {
                $entries = $this->collapseProjectDay($filtered);
            } else {
                $entries = $filtered;
            }

            // Totales del dia
            $dayTotals = [];
            foreach ($entries as $entry) {
                $minutes = $entry['duration_minutes'] ?? $entry['minutes'] ?? 0;
                $code    = $entry['project']?->code
                    ?? $entry['project_code']
                    ?? null;
                $name    = $entry['project']?->name
                    ?? $entry['project_name']
                    ?? null;

                $key = $code ?? '__none__';
                $dayTotals[$key] = ($dayTotals[$key] ?? 0) + $minutes;
                $totals[$key]     = ($totals[$key] ?? 0) + $minutes;
                $names[$key]      = $name;
                $grand           += $minutes;
            }

            if (! empty($entries)) {
                $days[$cursor->format('Y-m-d')] = [
                    'date'     => $cursor,
                    'sessions' => $entries,
                    'totals'   => $this->totalsArray($dayTotals, $names),
                ];
            }

            $cursor = $cursor->addDay();
        }

        return new Report(
            query:        $query,
            days:         $days,
            grandTotals:  $this->totalsArray($totals, $names),
            totalMinutes: $grand,
        );
    }

    public function render(Report $report): string
    {
        return $this->rendererFor($report->query->format)->render($report);
    }

    public function contentTypeFor(string $format): string
    {
        return $this->rendererFor($format)->contentType();
    }

    private function rendererFor(string $format): Renderer
    {
        return match ($format) {
            ExportQuery::FORMAT_MD  => new MarkdownRenderer(),
            ExportQuery::FORMAT_CSV => new CsvRenderer(),
            default                  => new TxtRenderer(),
        };
    }

    /**
     * Convierte sesiones en una fila por (proyecto, dia) con summary
     * sintetico que concatena los summaries de las sesiones.
     */
    private function collapseProjectDay(array $sessions): array
    {
        $byCode = [];
        foreach ($sessions as $s) {
            $code = $s['project']?->code ?? '__none__';
            $name = $s['project']?->name ?? null;
            $byCode[$code] ??= [
                'project_code' => $s['project']?->code,
                'project_name' => $name,
                'minutes'      => 0,
                'project'      => $s['project'],
                'summaries'    => [],
                'evidence'     => collect(),
            ];
            $byCode[$code]['minutes']  += $s['duration_minutes'];
            $byCode[$code]['evidence'] = $byCode[$code]['evidence']->merge($s['evidence']);
            if (! empty($s['summary'])) {
                $byCode[$code]['summaries'][] = $s['summary'];
            }
        }

        $out = [];
        foreach ($byCode as $row) {
            $row['summary'] = implode(' ', array_unique($row['summaries']));
            unset($row['summaries']);
            $out[] = $row;
        }
        usort($out, fn ($a, $b) => $b['minutes'] <=> $a['minutes']);
        return $out;
    }

    private function totalsArray(array $minutesByCode, array $namesByCode): array
    {
        $out = [];
        foreach ($minutesByCode as $code => $minutes) {
            $out[] = [
                'project_code' => $code === '__none__' ? null : $code,
                'project_name' => $namesByCode[$code] ?? null,
                'minutes'      => $minutes,
            ];
        }
        usort($out, fn ($a, $b) => $b['minutes'] <=> $a['minutes']);
        return $out;
    }

    /**
     * Entradas manuales cuyo inicio cae en el día local indicado.
     *
     * @return Collection<int,ManualEntry>
     */
    private function manualEntriesForDay(CarbonImmutable $localDay, string $tz): Collection
    {
        $startLocal = $localDay->setTimezone($tz)->startOfDay();

        return ManualEntry::query()
            ->with('project')
            ->startingBetween(
                $startLocal->setTimezone('UTC'),
                $startLocal->addDay()->setTimezone('UTC'),
            )
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * Da a una entrada manual la forma de "sesión" para que el resto del
     * pipeline (filtros, agrupación, renderers) la trate igual.
     *
     * @return array<string,mixed>
     */
    private function manualEntryAsSession(ManualEntry $entry, string $tz): array
    {
        $summary = $entry->title;
        if ($entry->notes) {
            $summary .= ' — ' . $entry->notes;
        }

        return [
            'project'          => $entry->project,
            'status'           => 'manual',     // no es idle → pasa el filtro idle
            'confidence'       => null,         // null → pasa el filtro de confianza
            'confidence_label' => 'manual · ' . $entry->kind->label(),
            'starts_at_local'  => $entry->starts_at->copy()->setTimezone($tz),
            'ends_at_local'    => $entry->ends_at->copy()->setTimezone($tz),
            'duration_minutes' => $entry->durationMinutes(),
            'evidence'         => collect(),
            'summary'          => $summary,
        ];
    }
}
