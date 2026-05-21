@extends('layouts.app')

@section('title', 'Tareas')
@section('container', '')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
        <h1 class="text-xl font-semibold tracking-tight">Tareas</h1>
        <form method="GET" action="{{ route('tasks.index') }}">
            <select name="project" class="select text-sm" style="width:auto" onchange="this.form.submit()">
                <option value="">Todos los proyectos</option>
                @foreach ($projects as $pr)
                    <option value="{{ $pr->id }}" @selected($projectId === $pr->id)>{{ $pr->code }} · {{ $pr->name }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if ($errors->any())
        <div id="form-errors" class="card p-4 mb-4 border-rose-400/60 text-rose-700 dark:text-rose-300">
            <ul class="list-disc pl-5 space-y-0.5 text-sm">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div data-task-board class="grid grid-cols-4 gap-3 items-start">
        @foreach ($columns as $col)
            @php $colTasks = $tasks->get($col->value, collect()); @endphp
            <section class="card flex flex-col" style="min-height: 60vh">
                <header class="flex items-center justify-between gap-2 p-3 border-b divider">
                    <span class="text-sm font-medium">
                        {{ $col->label() }}
                        <span class="text-faint">{{ $colTasks->count() }}</span>
                    </span>
                    <button type="button" class="btn-ghost text-sm" data-modal-open="#task-new"
                            data-add-status="{{ $col->value }}" aria-label="Nueva tarea en {{ $col->label() }}">+</button>
                </header>
                <div class="task-list flex-1 p-2 space-y-2" data-task-list="{{ $col->value }}">
                    @foreach ($colTasks as $task)
                        @include('tasks.partials.card', ['task' => $task])
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    {{-- ─────────────── Modales ─────────────── --}}
    <dialog id="task-new" class="modal">
        <form method="POST" action="{{ route('tasks.store') }}" class="space-y-3">
            @csrf
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold">Nueva tarea</h3>
                <button type="button" class="btn-ghost" data-modal-close aria-label="Cerrar">✕</button>
            </div>
            @include('tasks.partials.form-fields')
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" class="btn-ghost" data-modal-close>Cancelar</button>
                <button type="submit" class="btn">Crear</button>
            </div>
        </form>
    </dialog>

    <dialog id="task-edit" class="modal">
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold">Editar tarea</h3>
            <button type="button" class="btn-ghost" data-modal-close aria-label="Cerrar">✕</button>
        </div>
        <form method="POST" data-task-edit-form class="space-y-3 mt-3">
            @csrf
            @method('PATCH')
            @include('tasks.partials.form-fields')
            <div class="flex items-center justify-between gap-2 pt-1">
                <button type="submit" form="task-delete-form"
                        class="btn-ghost text-rose-600 dark:text-rose-400 text-sm">Eliminar</button>
                <div class="flex gap-2">
                    <button type="button" class="btn-ghost" data-modal-close>Cancelar</button>
                    <button type="submit" class="btn">Guardar</button>
                </div>
            </div>
        </form>
        {{-- Form de borrado aparte: el botón "Eliminar" lo envía vía form="…" --}}
        <form method="POST" id="task-delete-form" data-task-delete-form
              data-confirm="¿Eliminar esta tarea?" data-confirm-button="Sí, eliminar">
            @csrf
            @method('DELETE')
        </form>
    </dialog>
@endsection
