@extends('layouts.app')

@section('title', 'Tareas archivadas')

@section('content')
    <div class="mb-5 flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Tareas archivadas</h1>
            <p class="text-sm text-muted mt-1">Tareas que retiraste del tablero. Puedes restaurarlas o borrarlas definitivamente.</p>
        </div>
        <a href="{{ route('tasks.index') }}" class="btn-ghost text-sm">← Volver al tablero</a>
    </div>

    @if ($tasks->isEmpty())
        <x-empty-state
            icon="trash"
            title="No has archivado nada"
            text="Las tareas que archives desde el tablero aparecerán aquí, listas para restaurar o borrar." />

    @else
        <div data-archived>
            {{-- Barra de selección en lote. El "seleccionar todo" siempre está
                 visible; las acciones aparecen cuando hay al menos una marcada.
                 Los <input name="ids[]"> los inyecta archived.js al enviar. --}}
            <div class="flex items-center justify-between gap-3 mb-3 min-h-8">
                <label class="flex items-center gap-2 text-sm text-muted cursor-pointer select-none">
                    <input type="checkbox" class="accent-emerald-500" data-select-all
                           aria-label="Seleccionar todas">
                    <span data-bulk-count>Seleccionar todas</span>
                </label>
                <div class="flex items-center gap-2 hidden" data-bulk-actions>
                    <button type="submit" form="bulk-restore-form" class="btn-ghost text-sm">
                        Restaurar
                    </button>
                    <button type="submit" form="bulk-force-form"
                            class="btn-ghost text-sm text-rose-600 dark:text-rose-400">
                        Borrar para siempre
                    </button>
                </div>
            </div>

            {{-- Forms en lote: vacíos en el server; archived.js clona los ids
                 seleccionados como <input name="ids[]"> antes de enviar. El de
                 borrado pide confirmación vía el handler genérico data-confirm. --}}
            <form method="POST" id="bulk-restore-form" action="{{ route('tasks.bulk-restore') }}" class="hidden">
                @csrf
            </form>
            <form method="POST" id="bulk-force-form" action="{{ route('tasks.bulk-force-destroy') }}" class="hidden"
                  data-confirm="¿Borrar definitivamente las tareas seleccionadas? Esta acción no se puede deshacer."
                  data-confirm-button="Sí, borrar para siempre">
                @csrf
                @method('DELETE')
            </form>

            <div class="card divide-y divider overflow-hidden">
                @foreach ($tasks as $task)
                    <div class="flex items-center gap-3 p-3" data-archived-row>
                        <input type="checkbox" class="accent-emerald-500 shrink-0"
                               data-row-check value="{{ $task->id }}"
                               aria-label="Seleccionar «{{ $task->title }}»">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                @if ($task->project)
                                    <span class="chip shrink-0">
                                        <span class="inline-block w-1.5 h-1.5 rounded-full mr-1"
                                              style="background-color: {{ $task->project->color }}"></span>{{ $task->project->code }}
                                    </span>
                                @endif
                                <span class="text-sm font-medium truncate">{{ $task->title }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs mt-1">
                                <span class="text-faint">Archivada</span>
                                @if ($task->deleted_at)
                                    <x-timestamp :at="$task->deleted_at" />
                                @endif
                                @if ($task->labels->isNotEmpty())
                                    <span>·</span>
                                    @foreach ($task->labels as $label)
                                        <span class="text-[11px] rounded-full px-1.5 py-0.5 label-chip-tint"
                                              style="--label-color: {{ $label->color }};">{{ $label->title }}</span>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <form method="POST" action="{{ route('tasks.restore', $task->id) }}">
                                @csrf
                                <button type="submit" class="btn-ghost text-xs"
                                        title="Restaurar al tablero">Restaurar</button>
                            </form>
                            <form method="POST" action="{{ route('tasks.force-destroy', $task->id) }}"
                                  data-confirm="¿Borrar «{{ $task->title }}» definitivamente? Esta acción no se puede deshacer."
                                  data-confirm-button="Sí, borrar para siempre">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-ghost text-xs text-rose-600 dark:text-rose-400"
                                        title="Borrar definitivamente">
                                    <x-icon name="trash" class="w-3.5 h-3.5" />
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
