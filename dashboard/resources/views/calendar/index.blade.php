@extends('layouts.app')

@section('title', "Calendario {$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT))

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                {{ ucfirst($firstDay->locale('es')->isoFormat('MMMM YYYY')) }}
            </h1>
            <p class="text-sm text-ink-400 mt-1">
                @if ($monthTotal > 0)
                    {{ intdiv($monthTotal, 60) }}h {{ $monthTotal % 60 }}m en el mes
                @else
                    Sin actividad registrada
                @endif
            </p>
        </div>

        <div class="flex items-center gap-1">
            <a class="btn-ghost" href="{{ route('calendar.month', ['ym' => $prevMonth]) }}">←</a>
            <a class="btn-ghost" href="{{ route('calendar.current') }}">Hoy</a>
            <a class="btn-ghost" href="{{ route('calendar.month', ['ym' => $nextMonth]) }}">→</a>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="grid grid-cols-7 border-b border-ink-800">
            @foreach (['lun','mar','mié','jue','vie','sáb','dom'] as $d)
                <div class="px-3 py-2 text-xs uppercase tracking-wider text-ink-500 text-center">{{ $d }}</div>
            @endforeach
        </div>

        <div class="grid grid-cols-7">
            @foreach ($weeks as $week)
                @foreach ($week as $cell)
                    @php
                        $isToday = $cell['date']->isSameDay(\Carbon\CarbonImmutable::now($tz));
                        $base = 'min-h-[110px] p-2 border-b border-r border-ink-800 last:border-r-0';
                        $color = $cell['in_month'] ? 'text-ink-200' : 'text-ink-700 bg-ink-950';
                    @endphp
                    <a href="{{ route('timeline.day', ['date' => $cell['date']->format('Y-m-d')]) }}"
                       class="{{ $base }} {{ $color }} {{ $isToday ? 'ring-1 ring-emerald-400/40' : '' }} hover:bg-ink-800 transition">
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm font-semibold">{{ $cell['date']->format('d') }}</span>
                            @if ($cell['total'] > 0)
                                <span class="text-[10px] font-mono text-ink-400">
                                    {{ intdiv($cell['total'], 60) }}h{{ str_pad($cell['total'] % 60, 2, '0', STR_PAD_LEFT) }}
                                </span>
                            @endif
                        </div>

                        @if (! empty($cell['projects']))
                            <ul class="mt-1.5 space-y-0.5">
                                @foreach (array_slice($cell['projects'], 0, 3) as $p)
                                    <li class="flex items-center gap-1 text-[10px]">
                                        @if ($p['project'])
                                            <span class="inline-block w-1.5 h-1.5 rounded-full flex-shrink-0"
                                                  style="background: {{ $p['project']->color ?? '#9ca3af' }}"></span>
                                            <span class="truncate" style="color: {{ $p['project']->color ?? '#9ca3af' }}">
                                                {{ $p['project']->code }}
                                            </span>
                                        @else
                                            <span class="text-ink-500 truncate">—</span>
                                        @endif
                                    </li>
                                @endforeach
                                @if (count($cell['projects']) > 3)
                                    <li class="text-[9px] text-ink-600">+{{ count($cell['projects']) - 3 }}</li>
                                @endif
                            </ul>
                        @endif
                    </a>
                @endforeach
            @endforeach
        </div>
    </div>
@endsection
