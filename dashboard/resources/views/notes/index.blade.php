@extends('layouts.app')

@section('title', __('notes.title'))
{{-- Notas usa todo el ancho del área de contenido (sin el cap max-w-6xl) --}}
@section('container', '')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold tracking-tight">{{ __('notes.title') }}</h1>
        <p class="text-sm text-muted mt-1">
            {{ $folders->count() }} {{ Str::plural('carpeta', $folders->count()) }}
        </p>
    </div>

    @if ($errors->any())
        <div id="form-errors" class="card p-4 mb-4 border-rose-400/60 text-rose-700 dark:text-rose-300">
            <ul class="list-disc pl-5 space-y-0.5 text-sm">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="card flex" style="height: 78vh">

        {{-- ─── Lista de notas (plegable) ─── --}}
        <div id="notes-list" class="w-64 shrink-0 border-r divider flex flex-col">
            <div class="flex items-center gap-1 p-2 border-b divider">
                <button type="button" class="btn-ghost shrink-0" data-panel-toggle="list"
                        title="{{ __('notes.toggle_list') }}" aria-label="{{ __('notes.collapse') }}">
                    <span data-pc-collapse aria-hidden="true">«</span>
                    <span data-pc-expand   aria-hidden="true">»</span>
                </button>
                <form method="GET" action="{{ route('notes.index') }}" class="panel-full flex-1 min-w-0">
                    <input type="search" name="q" value="{{ $search }}"
                           placeholder="{{ __('notes.search_ph') }}" class="input text-sm">
                </form>
            </div>
            {{-- Cabecera limpia ──────────────────────── --}}
            @if ($isTrash)
                <div class="panel-full px-3 py-2.5 border-b divider flex items-center justify-between gap-2">
                    <span class="text-sm font-medium inline-flex items-center gap-1.5"><x-icon name="trash" class="w-4 h-4" />{{ __('notes.trash') }}</span>
                    @if ($trashCount > 0)
                        <form method="POST" action="{{ route('notes.trash.empty') }}" class="shrink-0"
                              data-confirm="{{ __('notes.empty_trash_confirm', ['count' => $trashCount]) }}"
                              data-confirm-button="{{ __('notes.empty_trash_btn') }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-ghost text-xs text-rose-600 dark:text-rose-400">{{ __('notes.empty_trash_label') }}</button>
                        </form>
                    @endif
                </div>
            @elseif ($search !== '')
                <div class="panel-full px-3 py-2.5 border-b divider flex items-center justify-between gap-2">
                    <span class="text-sm font-medium truncate">{{ __('notes.search_results', ['query' => $search]) }}</span>
                    <a href="{{ route('notes.index', ['folder' => $folderId]) }}"
                       class="btn-ghost text-xs shrink-0">{{ __('notes.search_clear') }}</a>
                </div>
            @else
                <div class="panel-full px-3 pt-2.5 pb-2 border-b divider">
                    @if (isset($parentFolder) && $parentFolder)
                        <a href="{{ route('notes.index', ['folder' => $parentFolder->id, 'back' => 1]) }}"
                           class="text-xs text-muted hover:text-emerald-600 dark:hover:text-emerald-400 flex items-center gap-1 mb-1">
                            ‹ {{ $parentFolder->name }}
                        </a>
                    @endif
                    @if ($currentFolder)
                        <span class="folder-title-inline block text-sm font-semibold truncate cursor-default select-none"
                              data-folder-id="{{ $currentFolder->id }}"
                              title="{{ __('notes.dblclick_rename') }}">{{ $currentFolder->name }}</span>
                    @else
                        <span class="text-sm font-semibold">{{ __('notes.root_label') }}</span>
                    @endif
                    <div class="flex gap-1.5 mt-2">
                        <button type="button"
                                class="flex-1 btn-ghost text-xs py-1.5"
                                data-modal-open="#subfolder-new">{{ __('notes.new_subfolder') }}</button>
                        <button type="button"
                                class="flex-1 btn text-xs py-1.5"
                                data-modal-open="#note-new">{{ __('notes.new_note') }}</button>
                    </div>
                </div>
            @endif
            {{-- Lista --}}
            @php
                $animClass = $navBack ? 'notes-nav-back' : ($folderId ? 'notes-nav-forward' : '');
            @endphp
            <div class="panel-full flex-1 min-h-0 overflow-y-auto p-2 space-y-1 {{ $animClass }}">
                @if ($isTrash)
                    @forelse ($notes as $n)
                        <div class="flex items-start rounded hover:bg-ink-100 dark:hover:bg-ink-800">
                            <div class="flex-1 min-w-0 px-2 py-1.5">
                                <div class="text-sm font-medium truncate">
                                    <span class="mr-1">{{ $n->icon ?: '📄' }}</span>{{ $n->title }}
                                </div>
                                <div class="text-xs text-faint">{{ __('notes.deleted_ago', ['ago' => $n->deleted_at->diffForHumans()]) }}</div>
                            </div>
                            <form method="POST" action="{{ route('notes.restore', $n->id) }}" class="shrink-0">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-ghost text-xs">{{ __('notes.restore') }}</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-muted text-center py-6">{{ __('notes.empty_trash_msg') }}</p>
                    @endforelse
                @else
                    {{-- Subcarpetas --}}
                    @if (isset($subfolders) && $subfolders->isNotEmpty())
                        @foreach ($subfolders as $sf)
                            <a href="{{ route('notes.index', ['folder' => $sf->id]) }}"
                               class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-ink-100 dark:hover:bg-ink-800 group"
                               data-subfolder-id="{{ $sf->id }}"
                               data-subfolder-name="{{ $sf->name }}"
                               data-subfolder-parent="{{ $sf->parent_id }}">
                                <span class="text-base leading-none">{{ $sf->icon ?: '📁' }}</span>
                                <span class="subfolder-name-text text-sm font-medium truncate flex-1">{{ $sf->name }}</span>
                                <span class="text-faint text-xs group-hover:text-muted">›</span>
                            </a>
                        @endforeach
                        @if ($notes->isNotEmpty())
                            <div class="border-t divider my-1"></div>
                        @endif
                    @endif
                    @forelse ($notes as $n)
                        @php
                            $noteLink = $search !== ''
                                ? route('notes.index', ['q' => $search, 'note' => $n->id])
                                : route('notes.index', ['folder' => $folderId, 'note' => $n->id]);
                            $preview = $n->preview();
                        @endphp
                        <div class="flex items-start rounded
                                    {{ $currentNote && $currentNote->id === $n->id ? 'surface-soft' : 'hover:bg-ink-100 dark:hover:bg-ink-800' }}"
                             data-note-id="{{ $n->id }}"
                             data-note-title="{{ $n->title }}"
                             data-note-folder="{{ $n->folder_id }}">
                            <a href="{{ $noteLink }}" class="flex-1 min-w-0 px-2 py-1.5">
                                <div class="text-sm font-medium truncate">
                                    <span class="mr-1">{{ $n->icon ?: '📄' }}</span>{{ $n->title }}
                                </div>
                                @if ($preview !== '')
                                    <div class="text-xs text-muted truncate">{{ $preview }}</div>
                                @endif
                            </a>
                            <form method="POST" action="{{ route('notes.pin', $n) }}" class="shrink-0">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="px-2 py-1.5 {{ $n->pinned ? 'text-amber-500' : 'text-faint hover:text-amber-500' }}"
                                        title="{{ $n->pinned ? __('notes.unpin') : __('notes.pin') }}"
                                        aria-label="{{ $n->pinned ? __('notes.unpin_note') : __('notes.pin_note') }}"><x-icon name="star" class="w-3.5 h-3.5" /></button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-muted text-center py-6">
                            {{ $search !== '' ? __('notes.no_search_results') : __('notes.no_notes') }}
                        </p>
                    @endforelse
                @endif
            </div>
        </div>

        {{-- ─── Editor ─── --}}
        <div class="flex-1 min-w-0 p-4 flex flex-col">
            @if ($currentNote)
                <form method="POST" action="{{ route('notes.update', $currentNote) }}" data-note-form
                      class="flex-1 min-h-0 flex flex-col gap-3">
                    @csrf
                    @method('PATCH')
                    @if (! empty($breadcrumb))
                        {{-- Ruta de carpetas (breadcrumb) --}}
                        <nav class="shrink-0 flex items-center gap-1 flex-wrap text-xs text-muted">
                            @foreach ($breadcrumb as $crumb)
                                <a href="{{ route('notes.index', ['folder' => $crumb->id]) }}"
                                   class="hover:underline">{{ $crumb->icon ?: '📁' }} {{ $crumb->name }}</a>
                                @unless ($loop->last)<span class="text-faint">/</span>@endunless
                            @endforeach
                        </nav>
                    @endif
                    {{-- Cabecera tipo Notion: icono + título grande --}}
                    <div class="shrink-0 flex items-start gap-2">
                        <details class="shrink-0 relative">
                            <summary class="list-none cursor-pointer select-none text-3xl leading-none px-1"
                                     title="{{ __('notes.change_icon') }}">{{ $currentNote->icon ?: '📄' }}</summary>
                            <div class="absolute z-10 mt-1 p-2 rounded border divider bg-[var(--paper)] dark:bg-ink-900 shadow-lg">
                                @include('notes.partials.icon-field', ['value' => $currentNote->icon])
                            </div>
                        </details>
                        <input type="text" name="title" required maxlength="200"
                               value="{{ old('title', $currentNote->title) }}" placeholder="{{ __('notes.title_ph') }}" aria-label="{{ __('notes.title_ph') }}"
                               class="flex-1 min-w-0 bg-transparent border-0 px-0 py-1 text-2xl font-bold tracking-tight rounded
                                      placeholder:text-ink-300 dark:placeholder:text-ink-600">
                    </div>
                    <textarea name="body" rows="18" data-note-body
                              class="textarea font-mono flex-1 min-h-0"
                              placeholder="{{ __('notes.body_ph') }}">{{ old('body', $currentNote->body) }}</textarea>
                    {{-- El editor WYSIWYG (Tiptap) se monta aquí; ver resources/js/notes-editor.js.
                         El skeleton se retira cuando Tiptap termina de inicializar. --}}
                    <div data-note-editor class="flex-1 min-h-0">
                        <div data-note-skeleton class="note-skeleton">
                            <div class="note-skel-line note-skel-h1"></div>
                            <div class="note-skel-line" style="width:94%"></div>
                            <div class="note-skel-line" style="width:88%"></div>
                            <div class="note-skel-line" style="width:96%"></div>
                            <div class="note-skel-line" style="width:76%"></div>
                            <div class="note-skel-line" style="width:100%; margin-top:0.5rem"></div>
                            <div class="note-skel-line" style="width:91%"></div>
                            <div class="note-skel-line" style="width:84%"></div>
                            <div class="note-skel-line" style="width:100%"></div>
                            <div class="note-skel-line" style="width:68%"></div>
                        </div>
                    </div>
                    <div class="border-t divider pt-3 mt-1 shrink-0 flex items-center gap-4 flex-wrap">
                        <label class="flex flex-col gap-0.5">
                            <span class="text-xs text-muted font-medium">{{ __('notes.folder_select') }}</span>
                            <select name="folder_id" style="min-width: 15rem" class="select">
                                <option value="">{{ __('notes.folder_root') }}</option>
                                @foreach ($folderOptions as $fo)
                                    <option value="{{ $fo['id'] }}"
                                        @selected((int) old('folder_id', $currentNote->folder_id) === $fo['id'])>
                                        {{ $fo['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="flex flex-col gap-0.5">
                            <span class="text-xs text-muted font-medium">{{ __('notes.project_label') }}</span>
                            <select name="project_id" style="min-width: 15rem" class="select">
                                <option value="">{{ __('notes.no_project') }}</option>
                                @foreach ($projects as $pr)
                                    <option value="{{ $pr->id }}"
                                        @selected((int) old('project_id', $currentNote->project_id) === $pr->id)>
                                        {{ $pr->code }} · {{ $pr->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm mt-3.5">
                            <input type="checkbox" name="pinned" value="1" class="accent-emerald-500" @checked($currentNote->pinned)>
                            {{ __('notes.pinned_label') }}
                        </label>
                        <div class="ml-auto flex items-center gap-3 mt-3.5">
                            <span data-autosave-status class="text-xs text-muted"></span>
                            <button type="button" class="btn-ghost text-sm" data-copy-link
                                    data-url="{{ route('notes.index', ['note' => $currentNote->id]) }}">{{ __('notes.copy_link') }}</button>
                            <button type="submit" class="btn">{{ __('common.save') }}</button>
                        </div>
                    </div>
                </form>

                {{-- Wikilinks: enlaces salientes (lo que ESTA nota referencia
                     con [[…]]) y backlinks (quién la referencia). Los huérfanos
                     (target_note_id null) se muestran punteados — clickearlos
                     busca el título; al crear esa nota se adopta el huérfano. --}}
                @if ($outgoing->isNotEmpty())
                    <div class="mt-3 shrink-0 flex items-start gap-2 text-sm">
                        <span class="text-muted shrink-0 pt-1">{{ __('notes.links_to') }}</span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($outgoing as $link)
                                @if ($link->target)
                                    <a href="{{ route('notes.index', ['note' => $link->target->id]) }}"
                                       class="chip wikilink hover:bg-ink-200 dark:hover:bg-ink-700">{{ $link->target->icon ?: '📄' }} {{ $link->target->title }}</a>
                                @else
                                    <a href="{{ route('notes.index', ['q' => $link->target_title]) }}"
                                       class="chip wikilink wikilink--missing"
                                       title="No existe todavía — click para buscar / crear">+ {{ $link->target_title }}</a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($backlinks->isNotEmpty())
                    <div class="mt-2 shrink-0 flex items-start gap-2 text-sm">
                        <span class="text-muted shrink-0 pt-1">{{ __('notes.linked_from') }}</span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($backlinks as $bl)
                                <a href="{{ route('notes.index', ['note' => $bl->id]) }}"
                                   class="chip wikilink hover:bg-ink-200 dark:hover:bg-ink-700">{{ $bl->icon ?: '📄' }} {{ $bl->title }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('notes.destroy', $currentNote) }}" class="mt-3 text-right shrink-0"
                      data-confirm="{{ __('notes.delete_confirm', ['title' => $currentNote->title]) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-ghost text-rose-600 dark:text-rose-400 text-sm">{{ __('notes.delete_note') }}</button>
                </form>
            @elseif ($isTrash)
                <div class="flex-1 flex items-center justify-center text-center text-muted">
                    <div>
                        <p class="text-base inline-flex items-center gap-1.5"><x-icon name="trash" class="w-4 h-4" />{{ __('notes.trash_label') }}</p>
                        <p class="text-sm mt-1">{{ __('notes.trash_empty_label') }}</p>
                    </div>
                </div>
            @else
                <div class="flex-1 flex items-center justify-center text-center text-muted">
                    <div>
                        <p class="text-base">{{ __('notes.no_selection') }}</p>
                        <p class="text-sm mt-1">{!! __('notes.no_selection_hint') !!}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- ─────────────── Modales ─────────────── --}}
    <dialog id="note-new" class="modal">
        <form method="POST" action="{{ route('notes.store') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="folder_id" value="{{ $folderId }}">
            @include('layouts.partials.modal-header', ['title' => __('notes.new_note_title')])
            <label class="label">
                <span>{{ __('notes.title_ph') }}</span>
                <input type="text" name="title" required maxlength="200" class="input mt-1" placeholder="{{ __('notes.title_ph') }}">
            </label>
            <label class="label">
                <span>{{ __('notes.icon_label') }}</span>
                <div class="mt-1">@include('notes.partials.icon-field', ['value' => ''])</div>
            </label>
            <label class="label">
                <span>{{ __('notes.project_label') }}</span>
                <select name="project_id" class="select mt-1">
                    <option value="">{{ __('notes.no_project') }}</option>
                    @foreach ($projects as $pr)
                        <option value="{{ $pr->id }}">{{ $pr->code }} · {{ $pr->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn-ghost" data-modal-close>{{ __('common.cancel') }}</button>
                <button type="submit" class="btn">{{ __('common.create') }}</button>
            </div>
        </form>
    </dialog>

    <dialog id="subfolder-new" class="modal">
        <form method="POST" action="{{ route('note-folders.store') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="parent_id" value="{{ $currentFolder?->id }}">
            @include('layouts.partials.modal-header', ['title' => __('notes.new_folder_title')])
            <label class="label">
                <span>{{ __('common.name') }}</span>
                <input type="text" name="name" required maxlength="120" class="input mt-1" placeholder="{{ __('notes.folder_name_ph') }}">
            </label>
            @include('notes.partials.icon-field', ['value' => ''])
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn-ghost" data-modal-close>{{ __('common.cancel') }}</button>
                <button type="submit" class="btn">{{ __('common.create') }}</button>
            </div>
        </form>
    </dialog>

    @if ($currentFolder)
        <dialog id="folder-edit" class="modal">
            <form method="POST" action="{{ route('note-folders.update', $currentFolder) }}" class="space-y-3">
                @csrf
                @method('PATCH')
                @include('layouts.partials.modal-header', ['title' => __('notes.rename_folder_title')])
                <label class="label">
                    <span>{{ __('common.name') }}</span>
                    <input type="text" name="name" required maxlength="120" class="input mt-1"
                           value="{{ $currentFolder->name }}">
                </label>
                <label class="label">
                    <span>{{ __('notes.icon_label') }}</span>
                    <div class="mt-1">@include('notes.partials.icon-field', ['value' => $currentFolder->icon])</div>
                </label>
                <div class="modal-footer flex justify-end gap-2">
                    <button type="button" class="btn-ghost" data-modal-close>{{ __('common.cancel') }}</button>
                    <button type="submit" class="btn">{{ __('common.save') }}</button>
                </div>
            </form>
        </dialog>
    @endif

    {{-- Modal: mover nota --}}
    <dialog id="note-move" class="modal">
        @include('layouts.partials.modal-header', ['title' => __('notes.move_title')])
        <div class="space-y-3">
            <p class="text-sm text-muted">
                {!! __('notes.move_note_dest', ['title' => '<strong id="note-move-title" class="text-foreground font-semibold"></strong>']) !!}
            </p>
            <label class="label">
                <span>{{ __('common.folder') }}</span>
                <select id="note-move-select" class="select mt-1">
                    <option value="">{{ __('notes.root_option') }}</option>
                    @foreach ($folderOptions as $fo)
                        <option value="{{ $fo['id'] }}">{{ $fo['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn-ghost" data-modal-close>{{ __('common.cancel') }}</button>
                <button type="button" class="btn" id="note-move-confirm">{{ __('notes.move_btn') }}</button>
            </div>
        </div>
    </dialog>

    {{-- Modal: mover carpeta --}}
    <dialog id="folder-move" class="modal">
        @include('layouts.partials.modal-header', ['title' => __('notes.move_folder_title')])
        <div class="space-y-3">
            <p class="text-sm text-muted">
                {!! __('notes.move_folder_parent', ['name' => '<strong id="folder-move-title" class="text-foreground font-semibold"></strong>']) !!}
            </p>
            <label class="label">
                <span>{{ __('notes.parent_folder') }}</span>
                <select id="folder-move-select" class="select mt-1">
                    <option value="">{{ __('notes.root_option') }}</option>
                    @foreach ($folderOptions as $fo)
                        <option value="{{ $fo['id'] }}">{{ $fo['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn-ghost" data-modal-close>{{ __('common.cancel') }}</button>
                <button type="button" class="btn" id="folder-move-confirm">{{ __('notes.move_btn') }}</button>
            </div>
        </div>
    </dialog>

    <script>
        window.__NOTE_FOLDERS = {!! json_encode(
            $folderOptions->map(fn ($fo) => ['id' => $fo['id'], 'name' => $fo['name']])->values()
        ) !!};
    </script>
@endsection
