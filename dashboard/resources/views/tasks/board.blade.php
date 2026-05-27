@extends('layouts.app')

@section('title', 'Tareas')
@section('container', '')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
        <h1 class="text-xl font-semibold tracking-tight">Tareas</h1>
        <div class="flex items-center gap-3 flex-wrap">
            @if ($githubSync)
                <span class="text-xs text-faint">
                    {{ $lastSync ? 'Sincronizado ' . $lastSync->locale('es')->diffForHumans() : 'Sin sincronizar' }}
                </span>
                <form method="POST" action="{{ route('tasks.sync') }}" data-loading-form>
                    @csrf
                    <button type="submit" class="btn-ghost text-sm" data-loading-label="Sincronizando…">
                        <x-icon name="refresh" class="w-3.5 h-3.5" /> Sincronizar
                    </button>
                </form>
            @endif
            <form method="GET" action="{{ route('tasks.index') }}" class="flex gap-2">
                <select name="project" class="select text-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="">Todos los proyectos</option>
                    @foreach ($projects as $pr)
                        <option value="{{ $pr->id }}" @selected($projectId === $pr->id)>{{ $pr->code }} · {{ $pr->name }}</option>
                    @endforeach
                </select>
                <select name="priority" class="select text-sm" style="width:auto" onchange="this.form.submit()">
                    <option value="">Toda prioridad</option>
                    @foreach ($priorities as $p)
                        <option value="{{ $p->value }}" @selected($priority === $p->value)>{{ $p->label() }}</option>
                    @endforeach
                </select>
            </form>
        </div>
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
                    @forelse ($colTasks as $task)
                        @include('tasks.partials.card', ['task' => $task])
                    @empty
                        <button type="button"
                                class="w-full text-xs text-faint hover:text-ink-600 dark:hover:text-ink-300
                                       border border-dashed divider rounded-md py-3 transition"
                                data-modal-open="#task-new" data-add-status="{{ $col->value }}">
                            + añadir tarea
                        </button>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    {{-- ─────────────── Modales ─────────────── --}}
    <dialog id="task-new" class="modal">
        @include('layouts.partials.modal-header', ['title' => 'Nueva tarea'])
        <form method="POST" action="{{ route('tasks.store') }}" class="space-y-3">
            @csrf
            @include('tasks.partials.form-fields')
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" class="btn-ghost" data-modal-close>Cancelar</button>
                <button type="submit" class="btn">Crear</button>
            </div>
        </form>
    </dialog>

    <dialog id="task-edit" class="modal">
        @include('layouts.partials.modal-header', ['title' => 'Editar tarea'])
        <form method="POST" data-task-edit-form class="space-y-3">
            @csrf
            @method('PATCH')
            @include('tasks.partials.form-fields')
            <div class="flex items-center justify-between gap-2 pt-1">
                <button type="submit" form="task-delete-form" class="btn-ghost text-rose-600 dark:text-rose-400 text-sm inline-flex items-center gap-1">
                    <x-icon name="trash" class="w-3.5 h-3.5" /> Eliminar
                </button>
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

        {{-- Subtareas (gestionadas por AJAX desde kanban.js) --}}
        <section data-task-subtasks class="mt-4 pt-4 border-t divider">
            <div class="flex items-center justify-between mb-2">
                <h4 class="text-sm font-semibold">Subtareas</h4>
                <span class="text-xs text-faint" data-subtasks-progress></span>
            </div>
            <ul data-subtasks-list class="space-y-1 text-sm mb-2"></ul>
            <form data-subtasks-add class="flex gap-1.5">
                <input type="text" name="title" required maxlength="200"
                       class="input text-sm flex-1" placeholder="Nueva subtarea — Enter para añadir">
                <button type="submit" class="btn-ghost text-sm" aria-label="Añadir subtarea">+</button>
            </form>
        </section>

        {{-- Comentarios (gestionados por AJAX desde kanban.js) --}}
        <section data-task-comments class="mt-4 pt-4 border-t divider">
            <h4 class="text-sm font-semibold mb-2">Comentarios</h4>
            <ul data-comments-list class="space-y-2 text-sm mb-2"></ul>
            <form data-comments-add class="flex gap-1.5">
                <textarea name="body" required maxlength="5000" rows="2"
                          class="textarea text-sm flex-1" placeholder="Añadir un comentario…"></textarea>
                <button type="submit" class="btn-ghost text-sm self-end" aria-label="Publicar comentario">Publicar</button>
            </form>
        </section>
    </dialog>
@endsection
