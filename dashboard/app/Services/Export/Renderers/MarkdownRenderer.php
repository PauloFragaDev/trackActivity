<?php

namespace App\Services\Export\Renderers;

use App\Services\Export\ExportQuery;
use App\Services\Export\Report;

class MarkdownRenderer implements Renderer
{
    public function contentType(): string
    {
        return 'text/markdown; charset=utf-8';
    }

    public function render(Report $report): string
    {
        $q = $report->query;
        $fromStr = $q->fromLocal->format('Y-m-d');
        $toStr   = $q->toLocal->copy()->subDay()->format('Y-m-d');

        $out = [];
        $out[] = "# Timesheet · {$fromStr} → {$toStr}";
        $out[] = '';

        foreach ($report->days as $day) {
            $dateLabel = ucfirst($day['date']->locale('es')->isoFormat('dddd D MMM YYYY'));
            $out[] = "## {$dateLabel}";
            $out[] = '';

            foreach ($day['sessions'] as $s) {
                if ($q->groupBy === ExportQuery::GROUP_PROJECT_DAY) {
                    $out[] = $this->renderProjectDay($s);
                } else {
                    $out[] = $this->renderSession($s);
                }
            }
        }

        $out[] = '';
        $out[] = '## Totales';
        $out[] = '';
        $out[] = '| Proyecto | Tiempo |';
        $out[] = '|----------|--------|';
        foreach ($report->grandTotals as $row) {
            $code  = $row['project_code'] ?? '(sin proyecto)';
            $hours = intdiv($row['minutes'], 60);
            $mins  = $row['minutes'] % 60;
            $out[] = "| `{$code}` | {$hours}h {$mins}m |";
        }
        $hT = intdiv($report->totalMinutes, 60);
        $mT = $report->totalMinutes % 60;
        $out[] = "| **TOTAL** | **{$hT}h {$mT}m** |";
        $out[] = '';

        return implode("\n", $out);
    }

    private function renderSession(array $s): string
    {
        $start = $s['starts_at_local']->format('H:i');
        $end   = $s['ends_at_local']->format('H:i');
        $proj  = $s['project']?->code ?? '(sin proyecto)';
        $conf  = $s['confidence_label'];

        $lines = [];
        $lines[] = "### {$start} – {$end} · `{$proj}` _({$conf})_";
        if (! empty($s['summary'])) {
            $lines[] = $s['summary'];
        }
        $evidence = $s['evidence'] ?? null;
        if ($evidence && method_exists($evidence, 'count') && $evidence->count() > 0) {
            $lines[] = '';
            $lines[] = '<details><summary>Evidencia ('.$evidence->count().')</summary>';
            $lines[] = '';
            foreach ($evidence->take(15) as $e) {
                $bits = [
                    "`[{$e->source}]`",
                    addslashes((string) ($e->title ?? $e->repo_name ?? $e->url ?? $e->subject ?? '—')),
                ];
                if ($e->branch) $bits[] = "`{$e->branch}`";
                if ($e->modified_files) $bits[] = "+{$e->modified_files}";
                $lines[] = '- ' . implode(' · ', $bits);
            }
            if ($evidence->count() > 15) {
                $lines[] = '- … +' . ($evidence->count() - 15) . ' mas';
            }
            $lines[] = '';
            $lines[] = '</details>';
        }
        $lines[] = '';
        return implode("\n", $lines);
    }

    private function renderProjectDay(array $entry): string
    {
        $code  = $entry['project_code'] ?? '(sin proyecto)';
        $hours = intdiv($entry['minutes'], 60);
        $mins  = $entry['minutes'] % 60;
        $lines = [];
        $lines[] = "### `{$code}` · {$hours}h {$mins}m";
        if (! empty($entry['summary'])) {
            $lines[] = $entry['summary'];
        }
        $lines[] = '';
        return implode("\n", $lines);
    }
}
