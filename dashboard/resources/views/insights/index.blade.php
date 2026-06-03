@extends('layouts.app')

@section('title', 'Insights')

@section('content')
    @php
        $fmt = function (int $m): string {
            if ($m >= 60) { $h = intdiv($m, 60); $r = $m % 60; return $r ? "{$h}h {$r}m" : "{$h}h"; }
            return "{$m}m";
        };
        $maxProject = collect($metrics['by_project'])->max('minutes') ?: 1;
        $dateParam = request('date');
    @endphp

    <div class="mb-5 flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Insights</h1>
            <p class="text-sm text-muted mt-1">{{ $heading }}</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- Día / Semana --}}
            <div class="inline-flex rounded-lg border divider overflow-hidden text-sm">
                <a href="{{ route('insights.index', ['period' => 'day'] + ($dateParam ? ['date' => $dateParam] : [])) }}"
                   class="px-3 py-1.5 {{ $period === 'day' ? 'bg-ink-100 dark:bg-ink-800 font-medium' : 'hover:bg-ink-50 dark:hover:bg-ink-800/40' }}">Día</a>
                <a href="{{ route('insights.index', ['period' => 'week'] + ($dateParam ? ['date' => $dateParam] : [])) }}"
                   class="px-3 py-1.5 border-l divider {{ $period === 'week' ? 'bg-ink-100 dark:bg-ink-800 font-medium' : 'hover:bg-ink-50 dark:hover:bg-ink-800/40' }}">Semana</a>
            </div>
            {{-- Anterior / siguiente --}}
            <a class="btn-ghost" href="{{ route('insights.index', ['period' => $period, 'date' => $prev]) }}" title="Anterior">←</a>
            <a class="btn-ghost" href="{{ route('insights.index', ['period' => $period, 'date' => $next]) }}" title="Siguiente">→</a>
        </div>
    </div>

    {{-- Resumen narrativo --}}
    <div class="card p-4 mb-4">
        <p class="text-sm leading-relaxed">{{ $metrics['narrative'] }}</p>
    </div>

    {{-- Métricas de foco --}}
    <div class="grid gap-3 mb-4" style="grid-template-columns: repeat(auto-fill, minmax(min(11rem, 100%), 1fr));">
        @php
            $stats = [
                ['Tiempo activo', $fmt($metrics['active_minutes'])],
                ['Inactivo', $fmt($metrics['idle_minutes'])],
                ['Racha de foco', $fmt($metrics['longest_focus_minutes'])],
                ['Cambios de contexto', (string) $metrics['context_switches']],
                ['Deep-work', $metrics['deep_work_pct'] . '%'],
            ];
        @endphp
        @foreach ($stats as [$label, $value])
            <div class="card p-3">
                <div class="text-xs text-faint">{{ $label }}</div>
                <div class="text-lg font-semibold mt-0.5">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="grid gap-4" style="grid-template-columns: repeat(auto-fit, minmax(min(20rem, 100%), 1fr));">
        {{-- Reparto por proyecto --}}
        <div class="card p-4">
            <h2 class="text-sm font-semibold mb-3">Reparto por proyecto</h2>
            @forelse ($metrics['by_project'] as $p)
                @if ($p['minutes'] > 0)
                    <div class="mb-2">
                        <div class="flex items-center justify-between text-xs mb-0.5">
                            <span class="flex items-center gap-1.5 min-w-0">
                                <span class="inline-block w-2 h-2 rounded-full shrink-0" style="background-color: {{ $p['color'] }}"></span>
                                <span class="truncate">{{ $p['project_name'] }}</span>
                            </span>
                            <span class="text-faint shrink-0">{{ $fmt($p['minutes']) }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-ink-100 dark:bg-ink-800 overflow-hidden">
                            <span class="block h-full rounded-full"
                                  style="width: {{ (int) round(100 * $p['minutes'] / $maxProject) }}%; background-color: {{ $p['color'] }}"></span>
                        </div>
                    </div>
                @endif
            @empty
                <p class="text-sm text-faint">Sin actividad en este periodo.</p>
            @endforelse
        </div>

        {{-- Tendencias por proyecto (últimas 8 semanas) --}}
        <div class="card p-4">
            <h2 class="text-sm font-semibold mb-3">Tendencia por proyecto · 8 semanas</h2>
            @if (count($trend['series']) > 0)
                <canvas id="insights-trend" height="200" aria-label="Tendencia por proyecto"></canvas>
                <script id="insights-data" type="application/json">@json($trend)</script>
            @else
                <p class="text-sm text-faint">Aún no hay suficientes datos para una tendencia.</p>
            @endif
        </div>
    </div>
@endsection
