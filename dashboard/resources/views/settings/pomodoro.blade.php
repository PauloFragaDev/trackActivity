@extends('layouts.app')

@section('title', 'Pomodoro')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight">Pomodoro</h1>
        <p class="text-sm text-muted mt-1">Duración de los ciclos y meta diaria de foco.</p>
    </div>

    @if ($errors->any())
        <div class="card p-4 mb-4 border-rose-400/60 text-rose-700 dark:text-rose-300">
            <ul class="list-disc pl-5 space-y-0.5 text-sm">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('settings.pomodoro.save') }}" class="card p-5 max-w-xl space-y-4">
        @csrf

        <div class="grid grid-cols-2 gap-4">
            <label class="block">
                <span class="text-sm font-medium">Foco (min)</span>
                <input type="number" name="pomodoro_focus_min" min="5" max="120"
                       value="{{ old('pomodoro_focus_min', $config['pomodoro_focus_min']) }}"
                       class="input mt-1 w-full" required>
            </label>
            <label class="block">
                <span class="text-sm font-medium">Pausa corta (min)</span>
                <input type="number" name="pomodoro_short_break_min" min="1" max="30"
                       value="{{ old('pomodoro_short_break_min', $config['pomodoro_short_break_min']) }}"
                       class="input mt-1 w-full" required>
            </label>
            <label class="block">
                <span class="text-sm font-medium">Pausa larga (min)</span>
                <input type="number" name="pomodoro_long_break_min" min="5" max="60"
                       value="{{ old('pomodoro_long_break_min', $config['pomodoro_long_break_min']) }}"
                       class="input mt-1 w-full" required>
            </label>
            <label class="block">
                <span class="text-sm font-medium">Ciclos hasta pausa larga</span>
                <input type="number" name="pomodoro_cycles_until_long" min="2" max="10"
                       value="{{ old('pomodoro_cycles_until_long', $config['pomodoro_cycles_until_long']) }}"
                       class="input mt-1 w-full" required>
            </label>
        </div>

        <label class="block">
            <span class="text-sm font-medium">Meta diaria de foco (min)</span>
            <input type="number" name="pomodoro_daily_goal_min" min="15" max="720"
                   value="{{ old('pomodoro_daily_goal_min', $config['pomodoro_daily_goal_min']) }}"
                   class="input mt-1 w-full" required>
            <p class="text-xs text-faint mt-1">
                Cuántos minutos de foco quieres acumular al día. Se muestra en el inicio y dispara la racha.
            </p>
        </label>

        <div class="pt-2 flex items-center justify-between">
            <p class="text-xs text-faint">
                Tras cada ciclo de foco se abre el cierre breve (mood + nota). Las entradas {{ '< 1 min' }} se descartan.
            </p>
            <button type="submit" class="btn">Guardar</button>
        </div>
    </form>
@endsection
