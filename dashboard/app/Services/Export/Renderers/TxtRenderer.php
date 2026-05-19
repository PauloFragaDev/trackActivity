<?php

namespace App\Services\Export\Renderers;

use App\Services\Export\ExportQuery;
use App\Services\Export\Report;

class TxtRenderer implements Renderer
{
    public function contentType(): string
    {
        return 'text/plain; charset=utf-8';
    }

    public function render(Report $report): string
    {
        $out = [];
        foreach ($report->days as $day) {
            foreach ($day['sessions'] as $s) {
                if ($report->query->groupBy === ExportQuery::GROUP_PROJECT_DAY) {
                    $out[] = $this->renderProjectDay($day['date'], $s);
                } else {
                    $out[] = $this->renderSession($day['date'], $s);
                }
            }
        }

        // Totales finales
        $out[] = '';
        $out[] = str_repeat('—', 40);
        $out[] = 'Totales';
        $out[] = str_repeat('—', 40);
        foreach ($report->grandTotals as $row) {
            $code  = $row['project_code'] ?? '(sin proyecto)';
            $hours = intdiv($row['minutes'], 60);
            $mins  = $row['minutes'] % 60;
            $out[] = sprintf('%-12s %2dh %02dm', $code, $hours, $mins);
        }
        $h = intdiv($report->totalMinutes, 60);
        $m = $report->totalMinutes % 60;
        $out[] = str_repeat('-', 40);
        $out[] = sprintf('%-12s %2dh %02dm', 'TOTAL', $h, $m);

        return implode("\n", $out) . "\n";
    }

    private function renderSession(\Carbon\CarbonImmutable $date, array $session): string
    {
        $start = $session['starts_at_local']->format('H:i');
        $end   = $session['ends_at_local']->format('H:i');
        $proj  = $session['project']?->code ?? '(sin proyecto)';
        $conf  = $session['confidence_label'];
        $line  = sprintf('%s  %s - %s  %-10s  [%s]', $date->format('Y-m-d'), $start, $end, $proj, $conf);
        $body  = ! empty($session['summary']) ? '  ' . $this->wrap($session['summary'], 76, '    ') : '';
        return $body === '' ? $line : $line . "\n" . $body . "\n";
    }

    private function renderProjectDay(\Carbon\CarbonImmutable $date, array $entry): string
    {
        $code  = $entry['project_code'] ?? '(sin proyecto)';
        $hours = intdiv($entry['minutes'], 60);
        $mins  = $entry['minutes'] % 60;
        $line  = sprintf('%s  %-10s  %2dh %02dm', $date->format('Y-m-d'), $code, $hours, $mins);
        $body  = ! empty($entry['summary']) ? '  ' . $this->wrap($entry['summary'], 76, '    ') : '';
        return $body === '' ? $line : $line . "\n" . $body . "\n";
    }

    private function wrap(string $text, int $width, string $indent): string
    {
        return implode("\n" . $indent, explode("\n", wordwrap($text, $width, "\n", false)));
    }
}
