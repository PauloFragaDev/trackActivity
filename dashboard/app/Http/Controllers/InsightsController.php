<?php

namespace App\Http\Controllers;

use App\Services\InsightsService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página /insights: resumen automático + métricas de foco + tendencias.
 * Día o semana según ?period=day|week (con ?date=YYYY-MM-DD opcional).
 * Cálculo en vivo vía InsightsService.
 */
class InsightsController extends Controller
{
    public function index(Request $request, InsightsService $insights): View
    {
        $tz     = (string) config('tracker.display_timezone', 'UTC');
        $period = $request->input('period') === 'week' ? 'week' : 'day';

        $ref = $request->filled('date')
            ? CarbonImmutable::parse($request->input('date'), $tz)
            : CarbonImmutable::now($tz);

        if ($period === 'week') {
            $anchor  = $ref->startOfWeek(CarbonImmutable::MONDAY);
            $metrics = $insights->forWeek($anchor);
            $prev    = $anchor->subWeek()->toDateString();
            $next    = $anchor->addWeek()->toDateString();
            $heading = 'Semana del ' . $anchor->format('d/m/Y');
        } else {
            $anchor  = $ref->startOfDay();
            $metrics = $insights->forDay($anchor);
            $prev    = $anchor->subDay()->toDateString();
            $next    = $anchor->addDay()->toDateString();
            $heading = $anchor->isToday() ? 'Hoy' : $anchor->format('d/m/Y');
        }

        return view('insights.index', [
            'period'  => $period,
            'heading' => $heading,
            'metrics' => $metrics,
            'trend'   => $insights->projectTrend(8),
            'prev'    => $prev,
            'next'    => $next,
        ]);
    }
}
