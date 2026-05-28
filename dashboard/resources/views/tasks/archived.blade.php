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
            title="Sin tareas archivadas"
            text="Cuando archives una tarea aparecerá aquí." />

    @else
        <div class="card divide-y divider overflow-hidden">
            @foreach ($tasks as $task)
                @php
                    $tz = config('tracker.display_timezone', 'UTC');
                    $deleted = $task->deleted_at?->setTimezone($tz);
                @endphp
                <div class="flex items-center justify-between gap-3 p-3">
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
    @endif
@endsection
