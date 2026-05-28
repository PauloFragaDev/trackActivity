@extends('layouts.settings')

@section('title', 'Pomodoro')

@section('settings-content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight">Pomodoro</h1>
        <p class="text-sm text-muted mt-1">Duración de las fases y cuántos ciclos hasta la pausa larga.</p>
    </div>

    <form method="POST" action="{{ route('settings.pomodoro.save') }}" class="card p-5 max-w-xl space-y-4">
        @csrf

        <div class="grid grid-cols-2 gap-4">
            <label class="block">
                <span class="text-sm font-medium">Foco (min)</span>
                <input type="number" name="pomodoro_focus_min" min="5" max="120"
                       value="{{ old('pomodoro_focus_min', $config['pomodoro_focus_min']) }}"
                       class="input mt-1 w-full @error('pomodoro_focus_min') is-invalid @enderror" required>
                <x-field-error name="pomodoro_focus_min" />
            </label>
            <label class="block">
                <span class="text-sm font-medium">Pausa corta (min)</span>
                <input type="number" name="pomodoro_short_break_min" min="1" max="30"
                       value="{{ old('pomodoro_short_break_min', $config['pomodoro_short_break_min']) }}"
                       class="input mt-1 w-full @error('pomodoro_short_break_min') is-invalid @enderror" required>
                <x-field-error name="pomodoro_short_break_min" />
            </label>
            <label class="block">
                <span class="text-sm font-medium">Pausa larga (min)</span>
                <input type="number" name="pomodoro_long_break_min" min="5" max="60"
                       value="{{ old('pomodoro_long_break_min', $config['pomodoro_long_break_min']) }}"
                       class="input mt-1 w-full @error('pomodoro_long_break_min') is-invalid @enderror" required>
                <x-field-error name="pomodoro_long_break_min" />
            </label>
            <label class="block">
                <span class="text-sm font-medium">Ciclos hasta pausa larga</span>
                <input type="number" name="pomodoro_cycles_until_long" min="2" max="10"
                       value="{{ old('pomodoro_cycles_until_long', $config['pomodoro_cycles_until_long']) }}"
                       class="input mt-1 w-full @error('pomodoro_cycles_until_long') is-invalid @enderror" required>
                <x-field-error name="pomodoro_cycles_until_long" />
            </label>
        </div>

        <div class="pt-3 flex items-center justify-between border-t divider">
            <p class="text-xs text-faint">
                El timer corre en tu navegador y se sincroniza entre pestañas.
            </p>
            <button type="submit" class="btn">Guardar</button>
        </div>
    </form>
@endsection
