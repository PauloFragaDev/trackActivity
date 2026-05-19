<?php

namespace App\Services\Export\Renderers;

use App\Services\Export\ExportQuery;
use App\Services\Export\Report;

class CsvRenderer implements Renderer
{
    public function contentType(): string
    {
        return 'text/csv; charset=utf-8';
    }

    public function render(Report $report): string
    {
        $bom  = "\xEF\xBB\xBF";   // BOM para que Excel en Windows reconozca UTF-8
        $rows = [];
        $rows[] = $this->header($report->query->groupBy);

        foreach ($report->days as $day) {
            foreach ($day['sessions'] as $s) {
                $rows[] = $report->query->groupBy === ExportQuery::GROUP_PROJECT_DAY
                    ? $this->projectDayRow($day['date'], $s)
                    : $this->sessionRow($day['date'], $s);
            }
        }

        return $bom . $this->toCsv($rows);
    }

    private function header(string $groupBy): array
    {
        if ($groupBy === ExportQuery::GROUP_PROJECT_DAY) {
            return ['date', 'project_code', 'project_name', 'duration_minutes', 'summary'];
        }
        return ['date', 'start', 'end', 'duration_minutes', 'project_code', 'project_name', 'confidence', 'summary', 'evidence'];
    }

    private function sessionRow(\Carbon\CarbonImmutable $date, array $s): array
    {
        $evidence = $s['evidence'] ?? null;
        $evidenceList = '';
        if ($evidence && method_exists($evidence, 'take')) {
            $evidenceList = $evidence->take(20)->map(function ($e) {
                $bit = "[{$e->source}] " . (string) ($e->title ?? $e->repo_name ?? $e->url ?? $e->subject ?? '');
                if ($e->branch) $bit .= " ({$e->branch})";
                if ($e->modified_files) $bit .= " +{$e->modified_files}";
                return trim($bit);
            })->implode(' ; ');
        }
        return [
            $date->format('Y-m-d'),
            $s['starts_at_local']->format('H:i'),
            $s['ends_at_local']->format('H:i'),
            (string) $s['duration_minutes'],
            $s['project']?->code ?? '',
            $s['project']?->name ?? '',
            $s['confidence_label'],
            (string) ($s['summary'] ?? ''),
            $evidenceList,
        ];
    }

    private function projectDayRow(\Carbon\CarbonImmutable $date, array $entry): array
    {
        return [
            $date->format('Y-m-d'),
            $entry['project_code'] ?? '',
            $entry['project_name'] ?? '',
            (string) $entry['minutes'],
            (string) ($entry['summary'] ?? ''),
        ];
    }

    private function toCsv(array $rows): string
    {
        $h = fopen('php://temp', 'r+');
        foreach ($rows as $r) {
            fputcsv($h, $r, ',', '"', "\\");
        }
        rewind($h);
        $content = stream_get_contents($h);
        fclose($h);
        return $content;
    }
}
