@extends('layouts.app')

@section('title', isset($project) ? $project->code . ' · ' . $project->name : __('tasks.title'))
@section('container', '')

@section('content')
@if(isset($project))
    {{-- ── Cabecera: Kanban de proyecto específico ────────────────────── --}}
    <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3 min-w-0">
            <nav class="flex items-center gap-1.5 text-sm min-w-0">
                <a href="{{ route('team.projects.index') }}" class="text-faint hover:text-default shrink-0">{{ __('tasks.projects_back') }}</a>
                <span class="text-faint">/</span>
                <span class="flex items-center gap-1.5 font-semibold truncate">
                    <span class="w-2.5 h-2.5 rounded-sm shrink-0 inline-block" style="background:{{ $project->color ?? '#999' }}"></span>
                    {{ $project->code }} · {{ $project->name }}
                </span>
            </nav>
            @if($mode === 'team')
                <div id="identity-pastilla" class="flex items-center gap-1.5 text-sm ml-1 shrink-0"></div>
            @endif
        </div>
        @if(isset($members) && $members->isNotEmpty())
        <form method="GET" action="{{ route('team.projects.board', $project) }}">
            <div class="w-44">
                <select name="assignee" class="select text-sm" onchange="this.form.submit()">
                    <option value="">{{ __('tasks.all_team') }}</option>
                    @foreach($members as $member)
                        <option value="{{ $member->id }}" @selected(isset($assigneeId) && $assigneeId === $member->id)>
                            {{ $member->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
        @endif
    </div>
@else
    {{-- ── Cabecera: Kanban global (personal / equipo) ────────────────── --}}
    <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3">
            <h1 class="text-xl font-semibold tracking-tight">{{ __('tasks.title') }}</h1>
            <a href="{{ route('tasks.archived') }}" class="text-xs text-faint hover:underline">
                {{ __('tasks.archived_link') }}
            </a>
            @if(config('team.db_host') && env('APP_MODE') !== 'team_only' && \App\Services\ModuleVisibility::enabled('team'))
            <div class="flex items-center gap-1 bg-surface-2 rounded-lg p-0.5 text-sm">
                <a href="{{ route('tasks.index') }}" data-tab-link
                   class="px-3 py-1 rounded-md transition-colors {{ $mode === 'personal' ? 'bg-surface-1 shadow-sm font-medium' : 'text-faint hover:text-default' }}">
                    {{ __('tasks.personal') }}
                </a>
                <a href="{{ route('team.tasks.index') }}" data-tab-link
                   class="px-3 py-1 rounded-md transition-colors {{ $mode === 'team' ? 'bg-surface-1 shadow-sm font-medium' : 'text-faint hover:text-default' }}">
                    {{ __('tasks.team') }}
                </a>
            </div>
            @endif
            @if($mode === 'team')
                <div id="identity-pastilla" class="flex items-center gap-1.5 text-sm ml-2"></div>
            @endif
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            {{-- Filtros del board. Ancho fijo en wrappers para que Choices.js
                 NO ajuste el control al texto seleccionado (provocaría que el
                 layout salte cada vez que cambias de filtro). --}}
            <form method="GET" action="{{ $mode === 'team' ? route('team.tasks.index') : route('tasks.index') }}" class="flex gap-2">
                <div class="w-56">
                    <select name="project" class="select text-sm" onchange="this.form.submit()">
                        <option value="">{{ __('tasks.all_projects') }}</option>
                        @foreach ($projects as $pr)
                            <option value="{{ $pr->id }}" @selected($projectId === $pr->id)>{{ $pr->code }} · {{ $pr->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-44">
                    <select name="priority" class="select text-sm" onchange="this.form.submit()">
                        <option value="">{{ __('tasks.all_priority') }}</option>
                        @foreach ($priorities as $p)
                            <option value="{{ $p->value }}" @selected($priority === $p->value)>{{ $p->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @if(isset($mode) && $mode === 'team' && isset($members) && $members->isNotEmpty())
                <div class="w-44">
                    <select name="assignee" class="select text-sm" onchange="this.form.submit()">
                        <option value="">{{ __('tasks.all_team') }}</option>
                        @foreach($members as $member)
                            <option value="{{ $member->id }}" @selected(isset($assigneeId) && $assigneeId === $member->id)>
                                {{ $member->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @endif
            </form>
        </div>
    </div>
@endif

    {{-- Barra de búsqueda + chips de labels (filtrado client-side). JS persiste en localStorage. --}}
    <div class="mb-4 flex items-center gap-3 flex-wrap" data-board-filters>
        <div class="input-group flex-1 min-w-[14rem] max-w-md">
            <span class="input-group__prefix"><x-icon name="search" class="w-4 h-4" /></span>
            <input type="search" data-task-search class="input text-sm"
                   placeholder="{{ __('tasks.search_placeholder') }}" autocomplete="off" aria-label="Buscar tareas">
            <span class="input-group__suffix hidden" data-task-search-clear-wrap>
                <button type="button" class="icon-btn w-7 h-7"
                        data-task-search-clear aria-label="{{ __('tasks.clear_search') }}" title="{{ __('tasks.clear_search') }}">
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
                <button type="button" class="btn-ghost text-xs hidden" data-label-filters-clear>{{ __('tasks.clear_filters') }}</button>
            </div>
        @endif
        <span class="text-xs text-faint" data-filter-summary></span>
    </div>

    {{-- Los errores de validación se muestran inline en cada campo
         (ver tasks/partials/form-fields.blade.php + x-field-error). --}}

    <div data-task-board {{ isset($columnDraggable) && $columnDraggable ? 'data-columns-container' : '' }} class="flex gap-3 items-start overflow-x-auto pb-2">
        @foreach ($columns as $col)
            @php $colTasks = $tasks->get($col->value, collect()); @endphp
            <section class="card flex flex-col task-column" data-task-column="{{ $col->value }}"
                     {!! isset($columnDraggable) && $columnDraggable ? 'data-column="'.$col->value.'"' : '' !!}
                     style="min-height: 60vh">
                <header class="task-column__header flex items-center justify-between gap-1 p-3 border-b divider cursor-pointer select-none"
                        data-task-column-toggle title="{{ __('tasks.collapse_column') }}">
                    <span class="task-column__title text-sm font-medium flex items-center gap-1.5">
                        @if(isset($columnDraggable) && $columnDraggable)
                        <span data-column-handle
                              class="text-faint hover:text-default cursor-grab active:cursor-grabbing touch-none shrink-0"
                              onclick="event.stopPropagation()" title="{{ __('tasks.drag_column') }}">
                            <x-icon name="grip" class="w-3.5 h-3.5" />
                        </span>
                        @endif
                        <x-icon name="chevron-down" class="task-column__chevron text-faint w-3 h-3" />
                        {{ $col->label() }}
                        <span class="text-faint" data-column-count>{{ $colTasks->count() }}</span>
                    </span>
                    <span class="flex items-center gap-0.5 shrink-0">
                        <button type="button" class="icon-btn text-faint task-column__sort"
                                data-task-column-sort title="{{ __('tasks.sort_az') }}"
                                aria-label="{{ __('tasks.sort_column') }}"
                                onclick="event.stopPropagation()">
                            <x-icon name="sort-asc" class="w-3.5 h-3.5" />
                        </button>
                        <button type="button" class="icon-btn" data-modal-open="#task-new"
                                data-add-status="{{ $col->value }}"
                                onclick="event.stopPropagation()"
                                aria-label="{{ __('tasks.new_in_column', ['column' => $col->label()]) }}" title="{{ __('tasks.new_task') }}">
                            <x-icon name="plus" class="w-3.5 h-3.5" />
                        </button>
                    </span>
                </header>
                <div class="task-column__body flex-1 flex flex-col">
                    <div class="task-list flex-1 p-2" data-task-list="{{ $col->value }}">
                        @foreach ($colTasks as $task)
                            @include('tasks.partials.card', ['task' => $task])
                        @endforeach
                    </div>

                    {{-- Inline-add al pie de la columna. Enter crea con el status de esta columna. --}}
                    <form data-task-inline-add data-status="{{ $col->value }}"
                          method="POST" action="{{ $mode === 'team' ? route('team.tasks.store') : route('tasks.store') }}"
                          class="p-2 pt-0">
                        @csrf
                        <input type="hidden" name="status" value="{{ $col->value }}">
                        <input type="text" name="title" maxlength="200" required
                               class="input text-sm bg-transparent border-dashed"
                               placeholder="{{ __('tasks.add_task_inline') }}">
                    </form>
                </div>
            </section>
        @endforeach
    </div>

    {{-- ─────────────── Modales ─────────────── --}}
    <dialog id="task-new" class="modal">
        @include('layouts.partials.modal-header', ['title' => __('tasks.modal_new')])
        <form method="POST" action="{{ $mode === 'team' ? route('team.tasks.store') : route('tasks.store') }}" class="space-y-3">
            @csrf
            @include('tasks.partials.form-fields')
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn-ghost" data-modal-close>{{ __('tasks.cancel') }}</button>
                <button type="submit" class="btn">{{ __('tasks.create') }}</button>
            </div>
        </form>
    </dialog>

    <dialog id="task-edit" class="modal modal-lg">
        @include('layouts.partials.modal-header', ['title' => __('tasks.modal_edit')])

        @if($mode === 'team')
        <div id="modal-creator-info" class="flex items-center gap-2 px-1 pb-2 text-sm text-faint empty:hidden"></div>
        @endif

        {{-- IMPORTANTE: forms NO se anidan en HTML5. El navegador los
             des-anida en parse, lo que desconecta el botón Guardar de
             su form y los del modal click-bubble al <dialog>.
             Solución: cada form es hermano (no descendiente) del otro,
             y los botones del footer apuntan con `form="ID"` al form
             principal aunque estén fuera de él. --}}

        {{-- Form 1: borrado (Archivar). Oculto, lo dispara el botón con form="task-delete-form". --}}
        <form method="POST" id="task-delete-form" data-task-delete-form
              data-confirm="{{ __('tasks.archive_confirm') }}"
              data-confirm-button="{{ __('tasks.archive_confirm_btn') }}">
            @csrf
            @method('DELETE')
        </form>

        {{-- Dos columnas: izquierda = formulario + subtareas; derecha = panel
             de comentarios tipo chat. En pantallas estrechas se apilan. --}}
        <div class="task-edit-grid">
            <div class="task-edit-main min-w-0">
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
                            <x-icon name="check" class="w-3.5 h-3.5 text-emerald-500" /> {{ __('tasks.subtasks') }}
                        </h4>
                        <span class="text-xs text-faint font-mono" data-subtasks-progress></span>
                    </div>
                    <ul data-subtasks-list class="space-y-1 text-sm mb-2"></ul>
                    <form data-subtasks-add class="input-group">
                        <input type="text" name="title" required maxlength="200"
                               class="input text-sm" placeholder="{{ __('tasks.subtask_add_ph') }}">
                        <span class="input-group__suffix">
                            <button type="submit" class="icon-btn" aria-label="{{ __('tasks.subtask_add_btn') }}" title="{{ __('tasks.subtask_add_btn') }}">
                                <x-icon name="plus" class="w-3.5 h-3.5" />
                            </button>
                        </span>
                    </form>
                </section>
            </div>

            {{-- Comentarios: panel lateral tipo chat. Lista con scroll propio +
                 compositor fijado abajo. Gestionado por AJAX desde kanban.js. --}}
            <aside data-task-comments class="task-edit-chat">
                <h4 class="text-sm font-semibold mb-2 flex items-center gap-1.5 shrink-0">
                    <x-icon name="chat" class="w-3.5 h-3.5 text-sky-500" /> {{ __('tasks.comments') }}
                </h4>
                <ul data-comments-list class="task-chat__list"></ul>
                <form data-comments-add class="task-chat__compose">
                    <textarea name="body" data-mention required maxlength="5000" rows="1"
                              class="textarea text-sm w-full" placeholder="{{ __('tasks.comment_ph') }}"></textarea>
                    <button type="submit" class="icon-btn task-chat__send"
                            aria-label="{{ __('tasks.comment_send') }}" title="{{ __('tasks.comment_send') }}">
                        <x-icon name="chevron-right" class="w-4 h-4" />
                    </button>
                </form>
            </aside>
        </div>

        {{-- Footer: los botones submit usan `form="ID"` para apuntar a sus forms
             aunque vivan fuera de ellos. Esto es HTML estándar (HTML5 form attr). --}}
        <div class="modal-footer flex items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <button type="button" id="btn-modal-archive"
                        class="btn-ghost text-rose-600 dark:text-rose-400 text-sm inline-flex items-center gap-1">
                    <x-icon name="trash" class="w-3.5 h-3.5" /> {{ __('tasks.archive_btn') }}
                </button>
                @if($mode === 'personal' && config('team.db_host') && \App\Services\ModuleVisibility::enabled('team'))
                <button type="button" id="btn-transfer-to-team"
                        class="btn-ghost text-blue-600 dark:text-blue-400"
                        data-task-id="">
                    {{ __('tasks.transfer_team') }}
                </button>
                @endif
            </div>
            <div class="flex gap-2">
                <button type="button" id="btn-modal-edit" class="btn">{{ __('tasks.edit_btn') }}</button>
                <button type="button" id="btn-modal-cancel-edit" class="btn-ghost hidden">{{ __('tasks.cancel_edit') }}</button>
                <button type="submit" form="task-edit-main-form" id="btn-modal-save" class="btn hidden">{{ __('tasks.save') }}</button>
            </div>
        </div>
    </dialog>

@if($mode === 'team' && isset($members) && $members->isNotEmpty())
@php $activeMemberId = session('team_member_id') ? (int) session('team_member_id') : null @endphp
<dialog id="identity-modal" class="modal">
    @include('layouts.partials.modal-header', ['title' => __('tasks.who_are_you'), 'hint' => false])
    <p class="text-sm text-faint mb-4">{{ __('tasks.select_profile') }}</p>
    <div class="space-y-1.5" id="identity-list">
        @foreach($members as $member)
        @php $isActive = $activeMemberId === $member->id @endphp
        <button type="button"
                class="identity-option w-full flex items-center gap-3 p-3 rounded-lg transition-colors text-left
                       @if($isActive) bg-ink-100 dark:bg-ink-800 ring-2 ring-inset ring-ink-300 dark:ring-ink-600
                       @else hover:bg-ink-100 dark:hover:bg-ink-800 @endif"
                data-member-id="{{ $member->id }}"
                data-member-name="{{ $member->name }}">
            <span class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-white flex-shrink-0 text-sm"
                  style="background-color: {{ $member->color }}">{{ $member->initials() }}</span>
            <span class="font-medium flex-1">{{ $member->name }}</span>
            @if($isActive)
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full text-white"
                  style="background-color: {{ $member->color }}">{{ __('tasks.you_badge') }}</span>
            @endif
        </button>
        @endforeach
    </div>
</dialog>
@endif

<script>
window.KANBAN_MODE = '{{ $mode }}';
window.KANBAN_ROUTES = {
    store:         '{{ $mode === "team" ? route("team.tasks.store")   : route("tasks.store") }}',
    move:          '{{ $mode === "team" ? "/team/tasks"               : "/tasks" }}',
    update:        '{{ $mode === "team" ? "/team/tasks"               : "/tasks" }}',
    peek:          '{{ $mode === "team" ? route("team.tasks.peek")    : route("tasks.peek") }}',
    checkboxStore: '{{ $mode === "team" ? "/team/tasks" : "/tasks" }}',
    commentStore:  '{{ $mode === "team" ? "/team/tasks" : "/tasks" }}',
    identityStore:   '{{ route("team.identity.store") }}',
    identityClear:   '{{ route("team.identity.destroy") }}',
    transferPreview: '/tasks',
    transfer:        '/tasks',
    @if(isset($columnDraggable) && $columnDraggable)
    updateColumns:   '{{ route("team.projects.columns", $project) }}',
    @endif
};
@if($mode === 'team')
window.SUPABASE_URL      = '{{ config("team.supabase_url") }}';
window.SUPABASE_ANON_KEY = '{{ config("team.supabase_anon_key") }}';
window.TEAM_MEMBER_ID   = '{{ session("team_member_id") ?? "" }}';
window.TEAM_MEMBER_NAME = '{{ session("team_member_name") ?? "" }}';
@php $teamMembersData = isset($members) ? $members->map(fn($m) => ['id' => $m->id, 'name' => $m->name, 'color' => $m->color, 'initials' => $m->initials()])->values() : []; @endphp
window.TEAM_MEMBERS     = @json($teamMembersData);
@endif
</script>
@endsection
