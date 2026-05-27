<?php

namespace App\Services;

use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Agregaciones para la vista /reports. Lee de time_blocks + manual_entries
 * (las dos fuentes de "tiempo gastado") y de activity_events para el top
 * de apps. Todas las operaciones reciben un rango UTC + tz local para
 * que el bucketing por día respete la franja horaria del usuario.
 */
class ReportsService
{
    /** Duración en minutos vía julianday (SQLite). */
    private const DURATION = '(julianday(ends_at)-julianday(starts_at))*1440';

    /**
     * Minutos por proyecto en el rango, sumando time_blocks (auto) y
     * manual_entries. project_id null = "Sin proyecto".
     *
     * @return list<array{project_id:?int,project_code:?string,project_name:string,color:string,minutes:int}>
     */
    public function byProject(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $startUtc = $this->utc($start);
        $endUtc   = $this->utc($end);

        $blocks = DB::table('time_blocks')
            ->selectRaw('dominant_project_id as pid, SUM(' . self::DURATION . ') as minutes')
            ->where('status', '!=', 'idle')
            ->where('starts_at', '>=', $startUtc)
            ->where('starts_at', '<',  $endUtc)
            ->groupBy('dominant_project_id')
            ->get();

        $entries = DB::table('manual_entries')
            ->selectRaw('project_id as pid, SUM(' . self::DURATION . ') as minutes')
            ->where('starts_at', '>=', $startUtc)
            ->where('starts_at', '<',  $endUtc)
            ->groupBy('project_id')
            ->get();

        $totals = [];
        foreach ([$blocks, $entries] as $set) {
            foreach ($set as $r) {
                $key = $r->pid ?? 0;   // 0 = "sin proyecto"
                $totals[$key] = ($totals[$key] ?? 0) + (int) round((float) $r->minutes);
            }
        }

        $projects = Project::query()
            ->whereIn('id', array_filter(array_keys($totals)))
            ->get()->keyBy('id');

        $result = [];
        foreach ($totals as $key => $minutes) {
            if ($minutes <= 0) {
                continue;
            }
            $p = $key ? $projects->get($key) : null;
            $result[] = [
                'project_id'   => $p?->id,
                'project_code' => $p?->code,
                'project_name' => $p?->name ?? 'Sin proyecto',
                'color'        => $p?->color ?? '#94a3b8',
                'minutes'      => $minutes,
            ];
        }
        usort($result, fn ($a, $b) => $b['minutes'] <=> $a['minutes']);

        return $result;
    }

    /**
     * Minutos por día (en hora local) cubriendo todo el rango — rellena
     * los días sin actividad con 0 para que las gráficas no se rompan.
     *
     * @return list<array{date:CarbonImmutable,minutes:int}>
     */
    public function byDay(CarbonImmutable $start, CarbonImmutable $end, string $tz): array
    {
        $startUtc = $this->utc($start);
        $endUtc   = $this->utc($end);

        $blocks = DB::table('time_blocks')
            ->select('starts_at', DB::raw(self::DURATION . ' as m'))
            ->where('status', '!=', 'idle')
            ->where('starts_at', '>=', $startUtc)
            ->where('starts_at', '<',  $endUtc)
            ->get();

        $entries = DB::table('manual_entries')
            ->select('starts_at', DB::raw(self::DURATION . ' as m'))
            ->where('starts_at', '>=', $startUtc)
            ->where('starts_at', '<',  $endUtc)
            ->get();

        $byDay = [];
        foreach ($blocks->concat($entries) as $r) {
            $local = CarbonImmutable::parse($r->starts_at, 'UTC')->setTimezone($tz);
            $key   = $local->format('Y-m-d');
            $byDay[$key] = ($byDay[$key] ?? 0) + (int) round((float) $r->m);
        }

        $result = [];
        $cursor = $start->setTimezone($tz)->startOfDay();
        $endLoc = $end->setTimezone($tz);
        while ($cursor < $endLoc) {
            $key = $cursor->format('Y-m-d');
            $result[] = [
                'date'    => $cursor,
                'minutes' => $byDay[$key] ?? 0,
            ];
            $cursor = $cursor->addDay();
        }

        return $result;
    }

    /**
     * Top apps por frecuencia de eventos window (proxy de tiempo:
     * cada evento ≈ 15 s del intervalo del collector).
     *
     * @return list<array{app:string,minutes:int}>
     */
    public function topApps(CarbonImmutable $start, CarbonImmutable $end, int $limit = 5): array
    {
        $startUtc = $this->utc($start);
        $endUtc   = $this->utc($end);

        $rows = DB::table('activity_events')
            ->select('app', DB::raw('COUNT(*) as events'))
            ->where('source', 'window')
            ->whereNotNull('app')
            ->where('occurred_at', '>=', $startUtc)
            ->where('occurred_at', '<',  $endUtc)
            ->groupBy('app')
            ->orderByDesc('events')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'app'     => $r->app,
            'minutes' => (int) round($r->events * 15 / 60),
        ])->all();
    }

    private function utc(CarbonImmutable $when): string
    {
        return $when->setTimezone('UTC')->format('Y-m-d H:i:s');
    }
}
