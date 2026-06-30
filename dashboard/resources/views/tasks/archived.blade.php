@extends('layouts.app')

@section('title', __('tasks.archived_title'))

@section('content')
    <div class="mb-5 flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">{{ __('tasks.archived_title') }}</h1>
            <p class="text-sm text-muted mt-1">{{ __('tasks.archived_desc') }}</p>
        </div>
        <a href="{{ route('tasks.index') }}" class="btn-ghost text-sm">{{ __('tasks.archived_back') }}</a>
    </div>

    @if ($tasks->isEmpty())
        <x-empty-state
            icon="trash"
            :title="__('tasks.archived_empty_title')"
            :text="__('tasks.archived_empty_text')" />

    @else
        <div data-archived>
            {{-- Barra de selección en lote. El "seleccionar todo" siempre está
                 visible; las acciones aparecen cuando hay al menos una marcada.
                 Los <input name="ids[]"> los inyecta archived.js al enviar. --}}
            <div class="flex items-center justify-between gap-3 mb-3 min-h-8">
                <label class="flex items-center gap-2 text-sm text-muted cursor-pointer select-none">
                    <input type="checkbox" class="accent-emerald-500" data-select-all
                           aria-label="{{ __('tasks.archived_select_all') }}">
                    <span data-bulk-count>{{ __('tasks.archived_select_all') }}</span>
                </label>
                <div class="flex items-center gap-2 hidden" data-bulk-actions>
                    <button type="submit" form="bulk-restore-form" class="btn-ghost text-sm">
                        {{ __('tasks.archived_bulk_restore') }}
                    </button>
                    <button type="submit" form="bulk-force-form"
                            class="btn-ghost text-sm text-rose-600 dark:text-rose-400">
                        {{ __('tasks.archived_bulk_delete') }}
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
                  data-confirm="{{ __('tasks.archived_delete_confirm') }}"
                  data-confirm-button="{{ __('tasks.archived_delete_btn') }}">
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
                                <span class="text-faint">{{ __('tasks.archived_label') }}</span>
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
                                        title="{{ __('tasks.archived_restore_btn') }}">{{ __('tasks.archived_bulk_restore') }}</button>
                            </form>
                            <form method="POST" action="{{ route('tasks.force-destroy', $task->id) }}"
                                  data-confirm="{{ __('tasks.archived_delete_single_confirm', ['title' => $task->title]) }}"
                                  data-confirm-button="{{ __('tasks.archived_delete_btn') }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-ghost text-xs text-rose-600 dark:text-rose-400"
                                        title="{{ __('tasks.archived_force_btn') }}">
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
