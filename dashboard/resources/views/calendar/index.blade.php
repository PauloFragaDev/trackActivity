@extends('layouts.app')

@section('title', "Calendario {$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT))

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                {{ ucfirst($firstDay->locale('es')->isoFormat('MMMM YYYY')) }}
            </h1>
            <p class="text-sm text-muted mt-1">
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

    @if ($errors->any())
        <div class="card p-4 mb-4 border-rose-400/60 text-rose-700 dark:text-rose-300">
            <ul class="list-disc pl-5 space-y-0.5 text-sm">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card overflow-hidden">
        {{-- Cabecera dias semana --}}
        <div class="grid grid-cols-7 border-b divider">
            @foreach (['lun','mar','mié','jue','vie','sáb','dom'] as $d)
                <div class="px-3 py-2 text-xs uppercase tracking-wider text-muted text-center">{{ $d }}</div>
            @endforeach
        </div>

        {{-- Grid 6 semanas x 7 dias --}}
        <div class="grid grid-cols-7">
            @foreach ($weeks as $week)
                @foreach ($week as $cell)
                    @php
                        $isToday  = $cell['date']->isSameDay(\Carbon\CarbonImmutable::now($tz));
                        $offMonth = ! $cell['in_month'];
                    @endphp
                    <a href="{{ route('timeline.day', ['date' => $cell['date']->format('Y-m-d')]) }}"
                       class="min-h-[110px] p-2 border-b border-r divider last:border-r-0 transition block
                              {{ $offMonth ? 'opacity-50' : '' }}
                              {{ $isToday ? 'ring-1 ring-inset ring-emerald-400/50' : '' }}
                              hover:bg-ink-100 dark:hover:bg-ink-800">
                        <div class="flex items-baseline justify-between">
                            <span class="text-sm font-semibold {{ $offMonth ? 'text-muted' : '' }}">
                                {{ $cell['date']->format('d') }}
                            </span>
                            @if ($cell['total'] > 0)
                                <span class="text-[10px] font-mono text-muted">
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
                                                  style="background: {{ $p['project']->color ?? '#6b7280' }}"></span>
                                            <span class="truncate" style="color: {{ $p['project']->color ?? '#6b7280' }}">
                                                {{ $p['project']->code }}
                                            </span>
                                        @else
                                            <span class="text-muted truncate">—</span>
                                        @endif
                                    </li>
                                @endforeach
                                @if (count($cell['projects']) > 3)
                                    <li class="text-[9px] text-faint">+{{ count($cell['projects']) - 3 }}</li>
                                @endif
                            </ul>
                        @endif
                    </a>
                @endforeach
            @endforeach
        </div>
    </div>

    {{-- ─────── Añadir entrada manual a un día ─────── --}}
    <div class="mt-6">
        <details class="card p-4" @if ($errors->any() || session('overlap')) open @endif>
            <summary class="cursor-pointer text-sm font-medium select-none">
                + Añadir entrada manual <span class="text-muted">(reunión, corrección de horas…)</span>
            </summary>
            <form method="POST" action="{{ route('manual-entries.store') }}" class="mt-3 space-y-3 max-w-2xl">
                @csrf
                <input type="hidden" name="return" value="calendar">
                <label class="label">
                    <span>Día</span>
                    <input type="date" name="date" required class="input mt-1"
                           value="{{ old('date', $formDate) }}">
                </label>
                @include('timeline.partials.manual-entry-fields', ['entry' => null])
                <button type="submit" class="btn">Añadir entrada</button>
            </form>
        </details>
    </div>
@endsection
