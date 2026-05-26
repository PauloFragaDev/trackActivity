@extends('layouts.app')

@section('title', 'Etiquetas')

@section('content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight">Etiquetas</h1>
        <p class="text-sm text-muted mt-1">Paleta global de etiquetas para las tareas del tablero.</p>
    </div>

    @if ($errors->any())
        <div id="form-errors" class="card p-4 mb-4 border-rose-400/60 text-rose-700 dark:text-rose-300">
            <ul class="list-disc pl-5 space-y-0.5 text-sm">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- Crear --}}
    <form method="POST" action="{{ route('task-labels.store') }}" class="card p-4 mb-5">
        @csrf
        <h2 class="text-base font-semibold mb-3">Nueva etiqueta</h2>
        <div class="flex items-end gap-3 flex-wrap">
            <label class="label flex-1 min-w-[12rem]">
                <span>Título</span>
                <input type="text" name="title" required maxlength="60" class="input mt-1" placeholder="ej. urgente, frontend, revisión">
            </label>
            <label class="label">
                <span>Color</span>
                <select name="color" class="select mt-1">
                    @foreach ($colors as $c)
                        <option value="{{ $c['hex'] }}" style="background-color: {{ $c['hex'] }}1a; color: {{ $c['hex'] }}">{{ $c['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="btn">Crear</button>
        </div>
    </form>

    {{-- Lista --}}
    @if ($labels->isEmpty())
        <div class="card p-6 text-center text-muted text-sm">
            Aún no hay etiquetas. Crea la primera arriba.
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
                           class="input flex-1 text-sm">
                    <select name="color" class="select text-sm" style="width:auto">
                        @foreach ($colors as $c)
                            <option value="{{ $c['hex'] }}" @selected($c['hex'] === $label->color)>{{ $c['name'] }}</option>
                        @endforeach
                    </select>
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
