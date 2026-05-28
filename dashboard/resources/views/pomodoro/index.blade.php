@extends('layouts.app')

@section('title', 'Pomodoro')

@section('content')
    {{-- Una sola sección: timer grande + controles + contador de ciclos.
         Todo el estado vive en localStorage; el JS se cabla a estos data-*. --}}
    <div id="pomodoro-app"
         class="pomodoro-shell"
         data-focus-min="{{ $config['pomodoro_focus_min'] }}"
         data-short-break-min="{{ $config['pomodoro_short_break_min'] }}"
         data-long-break-min="{{ $config['pomodoro_long_break_min'] }}"
         data-cycles-until-long="{{ $config['pomodoro_cycles_until_long'] }}">

        <header class="pomodoro-header">
            <h1 class="text-xl font-semibold tracking-tight">Pomodoro</h1>
            <a href="{{ route('settings.pomodoro') }}" class="text-xs text-faint hover:underline">
                Ajustes
            </a>
        </header>

        <section class="pomodoro-card card">
            {{-- Badge fase --}}
            <div class="pomodoro-phase" data-pomodoro-phase>
                <span class="pomodoro-phase__dot" aria-hidden="true"></span>
                <span data-pomodoro-phase-label>Listo</span>
            </div>

            {{-- Display mm:ss --}}
            <div class="pomodoro-display" data-pomodoro-display>
                {{ str_pad((string) $config['pomodoro_focus_min'], 2, '0', STR_PAD_LEFT) }}:00
            </div>

            {{-- Ciclos de foco completados en la ronda actual --}}
            <p class="pomodoro-cycles text-sm text-muted">
                Ciclos: <span data-pomodoro-cycle-count>0</span> / {{ $config['pomodoro_cycles_until_long'] }}
            </p>

            {{-- Controles --}}
            <div class="pomodoro-controls">
                <button type="button" class="btn pomodoro-btn-primary"
                        data-pomodoro-action="primary"
                        data-label-idle="Empezar foco"
                        data-label-running="Pausar"
                        data-label-paused="Reanudar"
                        data-label-awaiting-break="Empezar pausa"
                        data-label-awaiting-focus="Empezar foco">
                    Empezar foco
                </button>
                <button type="button" class="btn-ghost"
                        data-pomodoro-action="skip" title="Saltar a la siguiente fase">
                    Saltar
                </button>
                <button type="button" class="btn-ghost text-rose-600 dark:text-rose-400"
                        data-pomodoro-action="reset" title="Reiniciar ciclo">
                    Reiniciar
                </button>
            </div>

            <p class="pomodoro-hint text-xs text-faint">
                Tras el foco aparece una pausa. Cada {{ $config['pomodoro_cycles_until_long'] }} ciclos
                la pausa es larga ({{ $config['pomodoro_long_break_min'] }} min).
            </p>
        </section>
    </div>
@endsection
