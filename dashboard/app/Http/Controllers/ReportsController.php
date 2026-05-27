<?php

namespace App\Http\Controllers;

use App\Services\ReportsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vista de informes: agrega los datos del periodo seleccionado y los
 * pasa al renderer (CSS bars y Chart.js).
 */
class ReportsController extends Controller
{
    public function index(Request $request, ReportsService $reports): View
    {
        $tz     = config('tracker.display_timezone', 'UTC');
        $period = $request->input('period', 'week');
        [$start, $end] = $this->resolveRange($period, $tz);

        $byProject = $reports->byProject($start, $end);
        $byDay     = $reports->byDay($start, $end, $tz);
        $topApps   = $reports->topApps($start, $end);

        $totalMinutes = array_sum(array_column($byProject, 'minutes'));
        $daysActive   = count(array_filter($byDay, fn ($r) => $r['minutes'] > 0));
        $projectCount = count(array_filter($byProject, fn ($r) => $r['project_id'] !== null));

        return view('reports.index', [
            'period'       => $period,
            'start'        => $start,
            'end'          => $end,
            'tz'           => $tz,
            'byProject'    => $byProject,
            'byDay'        => $byDay,
            'topApps'      => $topApps,
            'totalMinutes' => $totalMinutes,
            'daysActive'   => $daysActive,
            'projectCount' => $projectCount,
        ]);
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} */
    private function resolveRange(string $period, string $tz): array
    {
        $now = CarbonImmutable::now($tz);

        return match ($period) {
            'month' => [
                $now->startOfMonth(),
                $now->startOfMonth()->addMonth(),
            ],
            '30d' => [
                $now->startOfDay()->subDays(29),
                $now->startOfDay()->addDay(),
            ],
            default => [
                $now->startOfWeek(CarbonImmutable::MONDAY),
                $now->startOfWeek(CarbonImmutable::MONDAY)->addDays(7),
            ],
        };
    }
}
