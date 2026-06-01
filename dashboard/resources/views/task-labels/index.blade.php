@extends('layouts.settings')

@section('title', 'Etiquetas')

@section('settings-content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight">Etiquetas</h1>
        <p class="text-sm text-muted mt-1">Paleta global de etiquetas para las tareas del tablero.</p>
    </div>

    {{-- Crear --}}
    <form method="POST" action="{{ route('task-labels.store') }}" class="card p-4 mb-5">
        @csrf
        <h2 class="text-base font-semibold mb-3">Nueva etiqueta</h2>
        <div class="flex items-end gap-3 flex-wrap">
            <label class="label flex-1 min-w-[12rem]">
                <span>Título</span>
                <input type="text" name="title" required maxlength="60"
                       value="{{ old('title') }}"
                       class="input mt-1 @error('title') is-invalid @enderror"
                       placeholder="ej. urgente, frontend, revisión">
                <x-field-error name="title" />
            </label>
            <label class="label">
                <span>Color</span>
                <select name="color" class="select mt-1 @error('color') is-invalid @enderror">
                    @foreach ($colors as $c)
                        <option value="{{ $c['hex'] }}" @selected(old('color') === $c['hex']) style="background-color: {{ $c['hex'] }}1a; color: {{ $c['hex'] }}">{{ $c['name'] }}</option>
                    @endforeach
                </select>
                <x-field-error name="color" />
            </label>
            <button type="submit" class="btn">Crear</button>
        </div>
    </form>

    {{-- Lista --}}
    @if ($labels->isEmpty())
        <div class="card p-8 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-ink-100 dark:bg-ink-800 text-ink-500 mb-3">
                <x-icon name="filter" class="w-6 h-6" />
            </div>
            <h3 class="text-base font-semibold mb-1">Sin etiquetas todavía</h3>
            <p class="text-sm text-muted">Crea la primera con el formulario de arriba.</p>
        </div>
    @else
        <div class="card divide-y divider">
            @foreach ($labels as $label)
                <form method="POST" action="{{ route('task-labels.update', $label) }}"
                      class="flex items-center gap-3 px-4 py-3">
                    @csrf
                    @method('PATCH')
                    <span class="inline-block w-3 h-3 rounded-full shrink-0"
                          style="background-color: {{ $label->color }}"></span>
                    <input type="text" name="title" value="{{ $label->title }}" required maxlength="60"
                           class="input flex-1 min-w-[10rem] text-sm">
                    {{-- Wrapper de ancho fijo: Choices.js ignora el width inline
                         del <select> y se ensancha; sin acotarlo, aplastaba el
                         input del título a 0 (el nombre desaparecía). --}}
                    <div class="w-44 shrink-0">
                        <select name="color" class="select text-sm">
                            @foreach ($colors as $c)
                                <option value="{{ $c['hex'] }}" @selected($c['hex'] === $label->color)>{{ $c['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn-ghost text-sm">Guardar</button>
                    <button type="submit" form="del-{{ $label->id }}" class="btn-ghost text-sm text-rose-600 dark:text-rose-400">Eliminar</button>
                </form>
                <form method="POST" action="{{ route('task-labels.destroy', $label) }}" id="del-{{ $label->id }}"
                      data-confirm="¿Eliminar la etiqueta «{{ $label->title }}»? Desaparecerá de todas las tareas que la tengan."
                      data-confirm-button="Sí, eliminar" class="hidden">
                    @csrf @method('DELETE')
                </form>
            @endforeach
        </div>
    @endif
@endsection
