@extends('layouts.app')

@section('title', 'Tareas')
@section('container', '')

@section('content')
    <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-semibold tracking-tight">Tareas</h1>
            <a href="{{ route('tasks.archived') }}" class="text-xs text-faint hover:underline">
                Archivadas
            </a>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            {{-- Filtros del board. Ancho fijo en wrappers para que Choices.js
                 NO ajuste el control al texto seleccionado (provocaría que el
                 layout salte cada vez que cambias de filtro). --}}
            <form method="GET" action="{{ route('tasks.index') }}" class="flex gap-2">
                <div class="w-56">
                    <select name="project" class="select text-sm" onchange="this.form.submit()">
                        <option value="">Todos los proyectos</option>
                        @foreach ($projects as $pr)
                            <option value="{{ $pr->id }}" @selected($projectId === $pr->id)>{{ $pr->code }} · {{ $pr->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-44">
                    <select name="priority" class="select text-sm" onchange="this.form.submit()">
                        <option value="">Toda prioridad</option>
                        @foreach ($priorities as $p)
                            <option value="{{ $p->value }}" @selected($priority === $p->value)>{{ $p->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    {{-- Barra de búsqueda + chips de labels (filtrado client-side). JS persiste en localStorage. --}}
    <div class="mb-4 flex items-center gap-3 flex-wrap" data-board-filters>
        <div class="input-group flex-1 min-w-[14rem] max-w-md">
            <span class="input-group__prefix"><x-icon name="search" class="w-4 h-4" /></span>
            <input type="search" data-task-search class="input text-sm"
                   placeholder="Buscar por título…" autocomplete="off" aria-label="Buscar tareas">
            <span class="input-group__suffix hidden" data-task-search-clear-wrap>
                <button type="button" class="icon-btn w-7 h-7"
                        data-task-search-clear aria-label="Limpiar búsqueda" title="Limpiar">
                    <x-icon name="close" class="w-3.5 h-3.5" />
                </button>
            </span>
        </div>
        @if ($labels->isNotEmpty())
            <div class="flex items-center gap-1.5 flex-wrap" data-label-filters>
                @foreach ($labels as $label)
                    <button type="button" class="task-label-chip chip" data-label-filter="{{ $label->id }}"
                            style="color: {{ $label->color }}; border: 1px solid color-mix(in srgb, {{ $label->color }} 35%, transparent);"
                            title="Filtrar por «{{ $label->title }}»">
                        {{ $label->title }}
                    </button>
                @endforeach
                <button type="button" class="btn-ghost text-xs hidden" data-label-filters-clear>Limpiar</button>
            </div>
        @endif
        <span class="text-xs text-faint" data-filter-summary></span>
    </div>

    {{-- Los errores de validación se muestran inline en cada campo
         (ver tasks/partials/form-fields.blade.php + x-field-error). --}}

    <div data-task-board class="flex gap-3 items-start overflow-x-auto pb-2">
        @foreach ($columns as $col)
            @php $colTasks = $tasks->get($col->value, collect()); @endphp
            <section class="card flex flex-col task-column" data-task-column="{{ $col->value }}" style="min-height: 60vh">
                <header class="task-column__header flex items-center justify-between gap-1 p-3 border-b divider cursor-pointer select-none"
                        data-task-column-toggle title="Plegar columna">
                    <span class="task-column__title text-sm font-medium flex items-center gap-1.5">
                        <x-icon name="chevron-down" class="task-column__chevron text-faint w-3 h-3" />
                        {{ $col->label() }}
                        <span class="text-faint" data-column-count>{{ $colTasks->count() }}</span>
                    </span>
                    <span class="flex items-center gap-0.5 shrink-0">
                        <button type="button" class="icon-btn text-faint task-column__sort"
                                data-task-column-sort title="Ordenar A-Z (toggle)"
                                aria-label="Ordenar columna alfabéticamente"
                                onclick="event.stopPropagation()">
                            <x-icon name="sort-asc" class="w-3.5 h-3.5" />
                        </button>
                        <button type="button" class="icon-btn" data-modal-open="#task-new"
                                data-add-status="{{ $col->value }}"
                                onclick="event.stopPropagation()"
                                aria-label="Nueva tarea en {{ $col->label() }}" title="Nueva tarea">
                            <x-icon name="plus" class="w-3.5 h-3.5" />
                        </button>
                    </span>
                </header>
                <div class="task-column__body flex-1 flex flex-col">
                    <div class="task-list flex-1 p-2 space-y-2" data-task-list="{{ $col->value }}">
                        @foreach ($colTasks as $task)
                            @include('tasks.partials.card', ['task' => $task])
                        @endforeach
                    </div>

                    {{-- Inline-add al pie de la columna. Enter crea con el status de esta columna. --}}
                    <form data-task-inline-add data-status="{{ $col->value }}"
                          method="POST" action="{{ route('tasks.store') }}"
                          class="p-2 pt-0">
                        @csrf
                        <input type="hidden" name="status" value="{{ $col->value }}">
                        <input type="text" name="title" maxlength="200" required
                               class="input text-sm bg-transparent border-dashed"
                               placeholder="+ Añadir tarea — Enter">
                    </form>
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
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn-ghost" data-modal-close>Cancelar</button>
                <button type="submit" class="btn">Crear</button>
            </div>
        </form>
    </dialog>

    <dialog id="task-edit" class="modal modal-lg">
        @include('layouts.partials.modal-header', ['title' => 'Editar tarea'])

        {{-- IMPORTANTE: forms NO se anidan en HTML5. El navegador los
             des-anida en parse, lo que desconecta el botón Guardar de
             su form y los del modal click-bubble al <dialog>.
             Solución: cada form es hermano (no descendiente) del otro,
             y los botones del footer apuntan con `form="ID"` al form
             principal aunque estén fuera de él. --}}

        {{-- Form 1: borrado (Archivar). Oculto, lo dispara el botón con form="task-delete-form". --}}
        <form method="POST" id="task-delete-form" data-task-delete-form
              data-confirm="¿Archivar esta tarea? La podrás restaurar desde /tasks/archived."
              data-confirm-button="Sí, archivar">
            @csrf
            @method('DELETE')
        </form>

        {{-- Form 2: principal. Submit con el botón "Guardar" del footer. --}}
        <form method="POST" id="task-edit-main-form" data-task-edit-form class="space-y-4">
            @csrf
            @method('PATCH')
            @include('tasks.partials.form-fields')
        </form>

        {{-- Subtareas: form aparte, gestionado por AJAX desde kanban.js. --}}
        <section data-task-subtasks class="pt-4 mt-4 border-t divider">
            <div class="flex items-center justify-between mb-2">
                <h4 class="text-sm font-semibold flex items-center gap-1.5">
                    <x-icon name="check" class="w-3.5 h-3.5 text-emerald-500" /> Subtareas
                </h4>
                <span class="text-xs text-faint font-mono" data-subtasks-progress></span>
            </div>
            <ul data-subtasks-list class="space-y-1 text-sm mb-2"></ul>
            <form data-subtasks-add class="input-group">
                <input type="text" name="title" required maxlength="200"
                       class="input text-sm" placeholder="Nueva subtarea — Enter para añadir">
                <span class="input-group__suffix">
                    <button type="submit" class="icon-btn" aria-label="Añadir subtarea" title="Añadir">
                        <x-icon name="plus" class="w-3.5 h-3.5" />
                    </button>
                </span>
            </form>
        </section>

        {{-- Comentarios: form aparte, gestionado por AJAX. --}}
        <section data-task-comments class="pt-4 mt-4 border-t divider">
            <h4 class="text-sm font-semibold mb-2 flex items-center gap-1.5">
                <x-icon name="chat" class="w-3.5 h-3.5 text-sky-500" /> Comentarios
            </h4>
            <ul data-comments-list class="space-y-2 text-sm mb-2"></ul>
            <form data-comments-add class="space-y-2">
                <textarea name="body" required maxlength="5000" rows="3"
                          class="textarea text-sm w-full" placeholder="Añadir un comentario…"></textarea>
                <div class="flex justify-end">
                    <button type="submit" class="btn">Publicar</button>
                </div>
            </form>
        </section>

        {{-- Footer: los botones submit usan `form="ID"` para apuntar a sus forms
             aunque vivan fuera de ellos. Esto es HTML estándar (HTML5 form attr). --}}
        <div class="modal-footer flex items-center justify-between gap-2">
            <button type="submit" form="task-delete-form"
                    class="btn-ghost text-rose-600 dark:text-rose-400 text-sm inline-flex items-center gap-1">
                <x-icon name="trash" class="w-3.5 h-3.5" /> Archivar
            </button>
            <div class="flex gap-2">
                <button type="button" class="btn-ghost" data-modal-close>Cancelar</button>
                <button type="submit" form="task-edit-main-form" class="btn">Guardar</button>
            </div>
        </div>
    </dialog>
@endsection
