@extends('layouts.app')

@section('title', 'Timeline · ' . $day->isoFormat('dddd D MMM YYYY'))

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                {{ ucfirst($day->locale('es')->isoFormat('dddd D MMM YYYY')) }}
            </h1>
            <p class="text-sm text-ink-400 mt-1">
                {{ count($sessions) }} {{ Str::plural('sesión', count($sessions)) }}
                · {{ $eventCount }} señales crudas
                @if ($totalMinutes > 0)
                    · {{ intdiv($totalMinutes, 60) }}h {{ $totalMinutes % 60 }}m totales
                @endif
            </p>
        </div>

        <div class="flex items-center gap-1">
            <a class="btn-ghost" href="{{ route('timeline.day', ['date' => $prevDay]) }}" title="Día anterior">←</a>
            <a class="btn-ghost" href="{{ route('timeline.today') }}">Hoy</a>
            <a class="btn-ghost" href="{{ route('timeline.day', ['date' => $nextDay]) }}" title="Día siguiente">→</a>
        </div>
    </div>

    @if (count($sessions) === 0)
        <div class="card p-8 text-center text-ink-400">
            <p class="text-base">Sin actividad registrada este día.</p>
            <p class="mt-2 text-xs">
                Asegúrate de que el daemon está corriendo: <code class="chip">tracker run --foreground</code>
            </p>
        </div>
    @else
        {{-- Totales por proyecto --}}
        @if ($totals->isNotEmpty())
            <div class="card p-4 mb-6">
                <h2 class="text-xs uppercase tracking-wider text-ink-500 mb-3">Totales por proyecto</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach ($totals as $row)
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded bg-ink-800">
                            @if ($row['project'])
                                <span class="inline-block w-2 h-2 rounded-full"
                                      style="background: {{ $row['project']->color ?? '#777' }}"></span>
                                <span class="text-sm font-medium">{{ $row['project']->code }}</span>
                            @else
                                <span class="inline-block w-2 h-2 rounded-full bg-ink-500"></span>
                                <span class="text-sm font-medium text-ink-400">Sin proyecto</span>
                            @endif
                            <span class="text-xs font-mono text-ink-300">
                                {{ intdiv($row['minutes'], 60) }}h {{ $row['minutes'] % 60 }}m
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Timeline --}}
        <ol class="space-y-3">
            @foreach ($sessions as $session)
                <li class="card p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-mono text-sm text-ink-300">
                                    {{ $session['starts_at_local']->format('H:i') }}
                                    <span class="text-ink-600">→</span>
                                    {{ $session['ends_at_local']->format('H:i') }}
                                </span>
                                <span class="chip">{{ $session['duration_minutes'] }}m</span>
                                @if ($session['project'])
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium"
                                          style="background: {{ $session['project']->color ?? '#374151' }}20;
                                                 color: {{ $session['project']->color ?? '#9ca3af' }};">
                                        <span class="inline-block w-1.5 h-1.5 rounded-full"
                                              style="background: {{ $session['project']->color ?? '#9ca3af' }}"></span>
                                        {{ $session['project']->code }}
                                    </span>
                                @else
                                    <span class="chip text-ink-500">sin proyecto</span>
                                @endif
                                <span class="chip">{{ $session['app'] }}</span>
                            </div>

                            <details class="group mt-2">
                                <summary class="cursor-pointer text-xs text-ink-500 hover:text-ink-300 select-none">
                                    {{ $session['evidence']->count() }} señal{{ $session['evidence']->count() === 1 ? '' : 'es' }} ·
                                    <span class="underline-offset-2 group-hover:underline">ver evidencia</span>
                                </summary>
                                <ul class="mt-2 space-y-1 text-xs font-mono text-ink-400 border-l border-ink-800 pl-3">
                                    @foreach ($session['evidence']->take(20) as $event)
                                        <li class="truncate">
                                            <span class="text-ink-600">{{ \Carbon\Carbon::parse($event->occurred_at)->setTimezone($tz)->format('H:i:s') }}</span>
                                            <span class="text-ink-500">[{{ $event->source }}]</span>
                                            {{ $event->title ?? $event->repo_name ?? $event->url ?? $event->subject ?? '—' }}
                                        </li>
                                    @endforeach
                                    @if ($session['evidence']->count() > 20)
                                        <li class="text-ink-600">… y {{ $session['evidence']->count() - 20 }} más</li>
                                    @endif
                                </ul>
                            </details>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
@endsection
