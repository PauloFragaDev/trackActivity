@extends('layouts.settings')

@section('title', $isNew ? __('projects.new') : (__('projects.edit_prefix') . $project->code))

@section('settings-content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold tracking-tight">
            {{ $isNew ? __('projects.new') : __('projects.edit_prefix') . $project->code }}
        </h1>
        <a href="{{ route('projects.index') }}" class="btn-ghost">{{ __('projects.back') }}</a>
    </div>

    <form method="POST"
          action="{{ $isNew ? route('projects.store') : route('projects.update', $project) }}"
          class="card p-6 max-w-2xl space-y-5">
        @csrf
        @unless ($isNew)
            @method('PATCH')
        @endunless

        <div class="grid grid-cols-2 gap-4">
            <label class="label">
                <span>{{ __('projects.code_hint') }}</span>
                <input type="text" name="code" required
                       value="{{ old('code', $project->code) }}"
                       pattern="[A-Z0-9_\-]+"
                       maxlength="32"
                       placeholder="JASPER"
                       class="input font-mono @error('code') is-invalid @enderror">
                <x-field-error name="code" />
            </label>
            <label class="label">
                <span>{{ __('common.name') }}</span>
                <input type="text" name="name" required
                       value="{{ old('name', $project->name) }}"
                       maxlength="128"
                       placeholder="Jasper"
                       class="input @error('name') is-invalid @enderror">
                <x-field-error name="name" />
            </label>
        </div>

        <div class="grid grid-cols-2 gap-4 items-end">
            <label class="label">
                <span>{{ __('projects.color_hint') }}</span>
                <div class="flex items-center gap-2">
                    <input type="color" name="color"
                           value="{{ old('color', $project->color ?? '#10b981') }}"
                           class="h-10 w-12 rounded border divider bg-transparent cursor-pointer"
                           id="color-picker">
                    <input type="text" name="color_text" form="never"
                           value="{{ old('color', $project->color ?? '#10b981') }}"
                           class="input font-mono @error('color') is-invalid @enderror"
                           id="color-text"
                           oninput="document.getElementById('color-picker').value = this.value">
                </div>
                <x-field-error name="color" />
            </label>
        </div>

        <label class="label">
            <span>{{ __('common.description_optional') }}</span>
            <textarea name="description" rows="2" maxlength="1000"
                      class="input @error('description') is-invalid @enderror">{{ old('description', $project->description) }}</textarea>
            <x-field-error name="description" />
        </label>

        <div class="pt-2 border-t divider flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button type="submit" class="btn">
                    {{ $isNew ? __('projects.create_btn') : __('projects.save_changes') }}
                </button>
                <a href="{{ route('projects.index') }}" class="btn-ghost">{{ __('common.cancel') }}</a>
            </div>
            @unless ($isNew)
                <button type="button"
                        class="btn-danger"
                        onclick="document.getElementById('delete-form').requestSubmit()">
                    {{ __('projects.delete_btn') }}
                </button>
            @endunless
        </div>
    </form>

    @unless ($isNew)
        <form id="delete-form"
              method="POST"
              action="{{ route('projects.destroy', $project) }}"
              class="hidden"
              data-confirm="{{ __('projects.delete_confirm', ['code' => $project->code]) }}">
            @csrf
            @method('DELETE')
        </form>

        {{-- ─────── Mappings ─────── --}}
        <section class="mt-8">
            <h2 class="text-base font-semibold mb-3">{{ __('projects.mappings_title') }}</h2>
            <p class="text-sm text-muted mb-4">
                {!! __('projects.mappings_desc', ['url' => route('help')]) !!}
            </p>

            {{-- Nuevo mapping --}}
            <form method="POST" action="{{ route('projects.mappings.store', $project) }}"
                  class="card p-4 mb-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    <label class="label md:col-span-3">
                        <span>{{ __('projects.mapping_type') }}</span>
                        <select name="type" class="select">
                            @foreach (\App\Models\ProjectMapping::TYPES as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="label md:col-span-5">
                        <span>{{ __('projects.mapping_pattern') }}</span>
                        <input type="text" name="pattern" required class="input font-mono"
                               placeholder="{{ __('projects.mapping_pattern_ph') }}">
                    </label>
                    <label class="label md:col-span-2">
                        <span>{{ __('projects.mapping_bonus') }}</span>
                        <input type="number" name="weight_bonus" value="0" min="-10" max="10" class="input font-mono">
                    </label>
                    <label class="inline-flex items-center gap-2 md:col-span-1 text-sm">
                        <input type="checkbox" name="is_regex" value="1" class="accent-emerald-500">
                        {{ __('projects.mapping_regex') }}
                    </label>
                    <div class="md:col-span-1 text-right">
                        <button type="submit" class="btn w-full md:w-auto">{{ __('projects.mapping_add') }}</button>
                    </div>
                </div>
            </form>

            @if ($mappings->isEmpty())
                <div class="card p-6 text-center text-muted">
                    {{ __('projects.no_mappings') }}
                </div>
            @else
                <div class="card overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="surface-soft text-xs uppercase tracking-wider text-muted">
                            <tr>
                                <th class="text-left px-3 py-2">{{ __('projects.mapping_type_col') }}</th>
                                <th class="text-left px-3 py-2">{{ __('projects.mapping_pattern_col') }}</th>
                                <th class="text-center px-3 py-2">{{ __('projects.mapping_regex_col') }}</th>
                                <th class="text-right px-3 py-2">{{ __('projects.mapping_bonus_col') }}</th>
                                <th class="text-center px-3 py-2">{{ __('projects.mapping_status_col') }}</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mappings as $m)
                                <tr class="border-t divider {{ $m->enabled ? '' : 'opacity-50' }}">
                                    <td class="px-3 py-2 font-mono text-xs">{{ $m->type }}</td>
                                    <td class="px-3 py-2 font-mono break-all">{{ $m->pattern }}</td>
                                    <td class="px-3 py-2 text-center">{{ $m->is_regex ? '✓' : '—' }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $m->weight_bonus > 0 ? '+' . $m->weight_bonus : $m->weight_bonus }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <form method="POST" action="{{ route('projects.mappings.toggle', [$project, $m]) }}" class="inline">
                                            @csrf @method('PATCH')
                                            <button class="chip" title="Activar/desactivar">
                                                {{ $m->enabled ? __('projects.mapping_active') : __('projects.mapping_inactive') }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <form method="POST" action="{{ route('projects.mappings.destroy', [$project, $m]) }}" class="inline"
                                              data-confirm="{{ __('projects.mapping_delete_confirm') }}">
                                            @csrf @method('DELETE')
                                            <button class="btn-ghost text-rose-600 dark:text-rose-400">{{ __('common.delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @php $projectNotes = $project->notes()->orderByDesc('updated_at')->get(); @endphp
        <section class="mt-8">
            <h2 class="text-base font-semibold mb-3">{{ __('projects.notes_section') }}</h2>
            @if ($projectNotes->isEmpty())
                <div class="card p-6 text-center text-muted text-sm">
                    {{ __('projects.no_linked_notes') }}
                </div>
            @else
                <div class="card divide-y divider">
                    @foreach ($projectNotes as $note)
                        <a href="{{ route('notes.index', ['note' => $note->id]) }}"
                           class="flex items-center gap-2 px-4 py-2.5 text-sm hover:bg-ink-100 dark:hover:bg-ink-800">
                            <span>{{ $note->icon ?: '📄' }}</span>
                            <span class="flex-1 truncate">{{ $note->title }}</span>
                            <span class="shrink-0 text-xs text-faint">{{ $note->updated_at->diffForHumans() }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    @endunless
@endsection
