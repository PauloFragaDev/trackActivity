@extends('layouts.app')

@section('title', "Semana {$year}-W" . str_pad($week, 2, '0', STR_PAD_LEFT))

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                Semana {{ str_pad($week, 2, '0', STR_PAD_LEFT) }} · {{ $year }}
            </h1>
            <p class="text-sm text-muted mt-1">
                {{ $monday->isoFormat('D MMM') }} – {{ $sunday->isoFormat('D MMM YYYY') }}
                @if ($totalMinutes > 0)
                    · {{ intdiv($totalMinutes, 60) }}h {{ $totalMinutes % 60 }}m totales
                @endif
            </p>
        </div>

        <div class="flex items-center gap-1">
            <a class="btn-ghost" href="{{ route('timeline.week', ['week' => $prevWeek]) }}">←</a>
            <a class="btn-ghost" href="{{ route('timeline.this_week') }}">Esta</a>
            <a class="btn-ghost" href="{{ route('timeline.week', ['week' => $nextWeek]) }}">→</a>
        </div>
    </div>

    @if ($totals->isNotEmpty())
        <div class="card p-4 mb-6">
            <h2 class="text-xs uppercase tracking-wider text-muted mb-3">Totales por proyecto · semana</h2>
            <div class="flex flex-wrap gap-2">
                @foreach ($totals as $row)
                    <div class="surface-soft flex items-center gap-2 px-3 py-1.5 rounded">
                        @if ($row['project'])
                            <span class="inline-block w-2 h-2 rounded-full" style="background: {{ $row['project']->color ?? '#777' }}"></span>
                            <span class="text-sm font-medium">{{ $row['project']->code }}</span>
                        @else
                            <span class="inline-block w-2 h-2 rounded-full bg-ink-400 dark:bg-ink-500"></span>
                            <span class="text-sm font-medium text-muted">Sin proyecto</span>
                        @endif
                        <span class="text-xs font-mono text-muted">
                            {{ intdiv($row['minutes'], 60) }}h {{ $row['minutes'] % 60 }}m
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Grid 7 columnas (1 col en movil, 7 desde md) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-7 gap-3">
        @foreach ($days as $d)
            @php
                $isToday = $d['date']->isSameDay(\Carbon\CarbonImmutable::now($tz));
            @endphp
            <a href="{{ route('timeline.day', ['date' => $d['date']->format('Y-m-d')]) }}"
               class="card p-3 transition block min-h-[180px] hover:shadow-md
                      {{ $isToday ? 'ring-1 ring-emerald-400/60' : '' }}">
                <div class="flex items-baseline justify-between mb-2">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-muted">
                            {{ ucfirst($d['date']->locale('es')->isoFormat('ddd')) }}
                        </div>
                        <div class="text-lg font-semibold">{{ $d['date']->format('d') }}</div>
                    </div>
                    @if ($d['minutes'] > 0)
                        <span class="text-xs font-mono text-muted">
                            {{ intdiv($d['minutes'], 60) }}h {{ $d['minutes'] % 60 }}m
                        </span>
                    @endif
                </div>

                @if ($d['by_project']->isEmpty())
                    <p class="text-xs text-faint mt-4 text-center">—</p>
                @else
                    <ul class="space-y-1">
                        @foreach ($d['by_project']->take(4) as $row)
                            <li class="flex items-center justify-between gap-1 text-xs">
                                @if ($row['project'])
                                    <span class="inline-flex items-center gap-1 truncate"
                                          style="color: {{ $row['project']->color ?? '#6b7280' }}">
                                        <span class="inline-block w-1.5 h-1.5 rounded-full"
                                              style="background: {{ $row['project']->color ?? '#6b7280' }}"></span>
                                        {{ $row['project']->code }}
                                    </span>
                                @else
                                    <span class="text-muted truncate">—</span>
                                @endif
                                <span class="font-mono text-muted">
                                    {{ intdiv($row['minutes'], 60) }}h{{ str_pad($row['minutes'] % 60, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            </li>
                        @endforeach
                        @if ($d['by_project']->count() > 4)
                            <li class="text-[10px] text-faint">+ {{ $d['by_project']->count() - 4 }} más</li>
                        @endif
                    </ul>
                @endif
            </a>
        @endforeach
    </div>
@endsection
