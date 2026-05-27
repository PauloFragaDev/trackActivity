@extends('layouts.app')

@section('title', 'Informes')

@section('content')
    @php
        $fmt = fn (int $m) => $m <= 0
            ? '0m'
            : ($m >= 60 ? intdiv($m, 60) . 'h ' . ($m % 60) . 'm' : $m . 'm');
        $periodLabels = ['week' => 'Esta semana', 'month' => 'Este mes', '30d' => 'Últimos 30 días'];
        $avgDaily = count($byDay) ? (int) round($totalMinutes / count($byDay)) : 0;
        $maxProjectMinutes = ! empty($byProject) ? max(array_column($byProject, 'minutes')) : 1;
    @endphp

    <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Informes</h1>
            <p class="text-sm text-muted mt-1">
                {{ $start->locale('es')->isoFormat('D MMM') }}
                →
                {{ $end->copy()->subDay()->locale('es')->isoFormat('D MMM YYYY') }}
                · {{ $periodLabels[$period] ?? 'Periodo' }}
            </p>
        </div>
        <div class="flex items-center gap-1">
            @foreach ($periodLabels as $key => $label)
                <a href="{{ route('reports.index', ['period' => $key]) }}"
                   class="btn-ghost text-sm {{ $period === $key ? 'bg-ink-100 dark:bg-ink-800 text-ink-900 dark:text-ink-50 font-medium' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Tarjetas resumen --}}
    <div class="grid gap-3 md:grid-cols-4 mb-6">
        <div class="card p-4">
            <div class="text-xs text-muted uppercase tracking-wider">Total trackeado</div>
            <div class="text-2xl font-semibold mt-1">{{ $fmt($totalMinutes) }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs text-muted uppercase tracking-wider">Proyectos activos</div>
            <div class="text-2xl font-semibold mt-1">{{ $projectCount }}</div>
        </div>
        <div class="card p-4">
            <div class="text-xs text-muted uppercase tracking-wider">Días con actividad</div>
            <div class="text-2xl font-semibold mt-1">
                {{ $daysActive }}<span class="text-sm font-normal text-muted"> / {{ count($byDay) }}</span>
            </div>
        </div>
        <div class="card p-4">
            <div class="text-xs text-muted uppercase tracking-wider">Media diaria</div>
            <div class="text-2xl font-semibold mt-1">{{ $fmt($avgDaily) }}</div>
        </div>
    </div>

    @if ($totalMinutes === 0)
        <div class="card p-10 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-ink-100 dark:bg-ink-800 text-ink-500 mb-3">
                <x-icon name="clock" class="w-6 h-6" />
            </div>
            <h3 class="text-base font-semibold mb-1">Sin datos para este periodo</h3>
            <p class="text-sm text-muted">Asegúrate de que el tracker está en marcha o cambia de periodo.</p>
        </div>
    @else
        <div class="grid gap-4 md:grid-cols-2 mb-6">
            {{-- Por proyecto (CSS bars) --}}
            <div class="card p-4">
                <h2 class="text-xs font-medium uppercase tracking-wider text-muted mb-3">Por proyecto</h2>
                <div class="space-y-3">
                    @foreach ($byProject as $r)
                        <div>
                            <div class="flex items-baseline justify-between text-sm mb-1">
                                <span class="flex items-center gap-1.5 min-w-0">
                                    <span class="inline-block w-2 h-2 rounded-full shrink-0" style="background-color: {{ $r['color'] }}"></span>
                                    <span class="truncate">{{ $r['project_code'] ?? $r['project_name'] }}</span>
                                </span>
                                <span class="text-faint shrink-0 ml-2 font-mono text-xs">{{ $fmt($r['minutes']) }}</span>
                            </div>
                            <div class="h-2 bg-ink-100 dark:bg-ink-800 rounded-full overflow-hidden">
                                <div class="h-full rounded-full transition-all"
                                     style="width: {{ ($r['minutes'] / $maxProjectMinutes) * 100 }}%; background-color: {{ $r['color'] }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Por día (Chart.js) --}}
            <div class="card p-4">
                <h2 class="text-xs font-medium uppercase tracking-wider text-muted mb-3">Por día</h2>
                <div class="relative h-48">
                    <canvas id="chart-by-day"></canvas>
                </div>
            </div>
        </div>

        @if (! empty($topApps))
            <div class="card p-4">
                <h2 class="text-xs font-medium uppercase tracking-wider text-muted mb-3">Top apps</h2>
                <div class="space-y-1.5">
                    @foreach ($topApps as $app)
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="truncate">{{ $app['app'] }}</span>
                            <span class="text-faint shrink-0 ml-2 font-mono text-xs">≈ {{ $fmt($app['minutes']) }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-faint mt-3">Aproximación: 15 s por muestra de ventana activa.</p>
            </div>
        @endif

        <script id="reports-data" type="application/json">
            @json(['byDay' => collect($byDay)->map(fn ($r) => [
                'label'   => $r['date']->locale('es')->isoFormat('ddd D'),
                'minutes' => $r['minutes'],
            ])])
        </script>
    @endif
@endsection
