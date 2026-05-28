@extends('layouts.app')

@section('title', 'Notas')
{{-- Notas usa todo el ancho del área de contenido (sin el cap max-w-6xl) --}}
@section('container', '')

@section('content')
    <div class="mb-4">
        <h1 class="text-xl font-semibold tracking-tight">Notas</h1>
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
                        title="Plegar / desplegar notas" aria-label="Plegar notas">
                    <span data-pc-collapse aria-hidden="true">«</span>
                    <span data-pc-expand   aria-hidden="true">»</span>
                </button>
                <form method="GET" action="{{ route('notes.index') }}" class="panel-full flex-1 min-w-0">
                    <input type="search" name="q" value="{{ $search }}"
                           placeholder="Buscar notas…" class="input text-sm">
                </form>
            </div>
            {{-- Cabecera: carpeta actual o resultados de búsqueda --}}
            <div class="panel-full p-3 border-b divider flex items-center justify-between gap-2">
                @if ($isTrash)
                    <span class="text-sm font-medium truncate">🗑 Papelera</span>
                    @if ($trashCount > 0)
                        <form method="POST" action="{{ route('notes.trash.empty') }}" class="shrink-0"
                              data-confirm="¿Vaciar la papelera? Se eliminarán definitivamente {{ $trashCount }} nota(s). No se puede deshacer."
                              data-confirm-button="Sí, vaciar">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn-ghost text-xs text-rose-600 dark:text-rose-400">Vaciar</button>
                        </form>
                    @endif
                @elseif ($search !== '')
                    <span class="text-sm font-medium truncate">Búsqueda: «{{ $search }}»</span>
                    <a href="{{ route('notes.index', ['folder' => $folderId]) }}"
                       class="btn-ghost text-xs shrink-0">limpiar</a>
                @else
                    <span class="text-sm font-medium truncate">{{ $currentFolder?->name ?? 'Notas' }}</span>
                    <div class="flex items-center gap-1 shrink-0">
                        @if ($currentFolder)
                            <button type="button" class="btn-ghost text-xs" data-modal-open="#folder-edit" title="Renombrar carpeta" aria-label="Renombrar carpeta">✎</button>
                            <form method="POST" action="{{ route('note-folders.destroy', $currentFolder) }}" class="inline"
                                  data-confirm="¿Eliminar la carpeta «{{ $currentFolder->name }}»? Sus notas y subcarpetas pasarán a la raíz.">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-ghost text-xs text-rose-600 dark:text-rose-400" title="Eliminar carpeta" aria-label="Eliminar carpeta">🗑</button>
                            </form>
                        @endif
                        <button type="button" class="btn text-xs" data-modal-open="#note-new">+ Nota</button>
                    </div>
                @endif
            </div>
            {{-- Lista --}}
            <div class="panel-full flex-1 min-h-0 overflow-y-auto p-2 space-y-1">
                @if ($isTrash)
                    @forelse ($notes as $n)
                        <div class="flex items-start rounded hover:bg-ink-100 dark:hover:bg-ink-800">
                            <div class="flex-1 min-w-0 px-2 py-1.5">
                                <div class="text-sm font-medium truncate">
                                    <span class="mr-1">{{ $n->icon ?: '📄' }}</span>{{ $n->title }}
                                </div>
                                <div class="text-xs text-faint">Eliminada {{ $n->deleted_at->diffForHumans() }}</div>
                            </div>
                            <form method="POST" action="{{ route('notes.restore', $n->id) }}" class="shrink-0">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn-ghost text-xs">Restaurar</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-muted text-center py-6">La papelera está vacía.</p>
                    @endforelse
                @else
                    @forelse ($notes as $n)
                        @php
                            $noteLink = $search !== ''
                                ? route('notes.index', ['q' => $search, 'note' => $n->id])
                                : route('notes.index', ['folder' => $folderId, 'note' => $n->id]);
                            $preview = $n->preview();
                        @endphp
                        <div class="flex items-start rounded
                                    {{ $currentNote && $currentNote->id === $n->id ? 'surface-soft' : 'hover:bg-ink-100 dark:hover:bg-ink-800' }}">
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
                                        title="{{ $n->pinned ? 'Desfijar' : 'Fijar' }}"
                                        aria-label="{{ $n->pinned ? 'Desfijar nota' : 'Fijar nota' }}">★</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-sm text-muted text-center py-6">
                            {{ $search !== '' ? 'Sin resultados.' : 'Sin notas en esta carpeta.' }}
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
                                     title="Cambiar icono">{{ $currentNote->icon ?: '📄' }}</summary>
                            <div class="absolute z-10 mt-1 p-2 rounded border divider bg-white dark:bg-ink-900 shadow-lg">
                                @include('notes.partials.icon-field', ['value' => $currentNote->icon])
                            </div>
                        </details>
                        <input type="text" name="title" required maxlength="200"
                               value="{{ old('title', $currentNote->title) }}" placeholder="Título" aria-label="Título de la nota"
                               class="flex-1 min-w-0 bg-transparent border-0 px-0 py-1 text-2xl font-bold tracking-tight rounded
                                      placeholder:text-ink-300 dark:placeholder:text-ink-600">
                    </div>
                    <textarea name="body" rows="18"
                              class="textarea font-mono flex-1 min-h-0"
                              placeholder="Escribe en Markdown…">{{ old('body', $currentNote->body) }}</textarea>
                    {{-- El editor WYSIWYG (Crepe) se monta aquí; ver resources/js/notes-editor.js.
                         Altura fija: el contenido scrollea dentro de .ProseMirror (CSS en app.css);
                         el editor en sí no lleva overflow, para que el menú "/" no se recorte. --}}
                    <div data-note-editor hidden class="flex-1 min-h-0"></div>
                    <div class="flex items-center gap-3 flex-wrap shrink-0">
                        <label class="inline-flex items-center gap-1.5 text-sm">
                            <span class="text-muted">Carpeta</span>
                            <select name="folder_id" class="select" style="width:auto">
                                <option value="">— Sin carpeta —</option>
                                @foreach ($folders->sortBy('name') as $f)
                                    <option value="{{ $f->id }}"
                                        @selected((int) old('folder_id', $currentNote->folder_id) === $f->id)>
                                        {{ $f->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="inline-flex items-center gap-1.5 text-sm">
                            <span class="text-muted">Proyecto</span>
                            <select name="project_id" class="select" style="width:auto">
                                <option value="">— Sin proyecto —</option>
                                @foreach ($projects as $pr)
                                    <option value="{{ $pr->id }}"
                                        @selected((int) old('project_id', $currentNote->project_id) === $pr->id)>
                                        {{ $pr->code }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm">
                            <input type="checkbox" name="pinned" value="1" class="accent-emerald-500" @checked($currentNote->pinned)>
                            Fijada
                        </label>
                        <button type="button" class="btn-ghost text-sm" data-copy-link
                                data-url="{{ route('notes.index', ['note' => $currentNote->id]) }}">Copiar enlace</button>
                        <div class="ml-auto flex items-center gap-3">
                            <span data-autosave-status class="text-xs text-muted"></span>
                            <button type="submit" class="btn">Guardar</button>
                        </div>
                    </div>
                </form>

                @if ($backlinks->isNotEmpty())
                    {{-- Backlinks: notas que enlazan a esta --}}
                    <div class="mt-3 shrink-0 flex items-start gap-2 text-sm">
                        <span class="text-muted shrink-0 pt-1">🔗 Enlazada desde:</span>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($backlinks as $bl)
                                <a href="{{ route('notes.index', ['note' => $bl->id]) }}"
                                   class="chip hover:bg-ink-200 dark:hover:bg-ink-700">{{ $bl->icon ?: '📄' }} {{ $bl->title }}</a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('notes.destroy', $currentNote) }}" class="mt-3 text-right shrink-0"
                      data-confirm="¿Eliminar la nota «{{ $currentNote->title }}»?">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn-ghost text-rose-600 dark:text-rose-400 text-sm">Eliminar nota</button>
                </form>
            @elseif ($isTrash)
                <div class="flex-1 flex items-center justify-center text-center text-muted">
                    <div>
                        <p class="text-base">🗑 Papelera</p>
                        <p class="text-sm mt-1">Notas eliminadas. Restaura una para volver a editarla.</p>
                    </div>
                </div>
            @else
                <div class="flex-1 flex items-center justify-center text-center text-muted">
                    <div>
                        <p class="text-base">Ninguna nota seleccionada.</p>
                        <p class="text-sm mt-1">Crea una con <strong>+ Nota</strong> o elige una de la lista.</p>
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
            @include('layouts.partials.modal-header', ['title' => 'Nueva nota'])
            <label class="label">
                <span>Título</span>
                <input type="text" name="title" required maxlength="200" class="input mt-1" placeholder="Título de la nota">
            </label>
            <label class="label">
                <span>Icono</span>
                <div class="mt-1">@include('notes.partials.icon-field', ['value' => ''])</div>
            </label>
            <label class="label">
                <span>Proyecto</span>
                <select name="project_id" class="select mt-1">
                    <option value="">— Sin proyecto —</option>
                    @foreach ($projects as $pr)
                        <option value="{{ $pr->id }}">{{ $pr->code }} · {{ $pr->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn-ghost" data-modal-close>Cancelar</button>
                <button type="submit" class="btn">Crear</button>
            </div>
        </form>
    </dialog>

    @if ($currentFolder)
        <dialog id="folder-edit" class="modal">
            <form method="POST" action="{{ route('note-folders.update', $currentFolder) }}" class="space-y-3">
                @csrf
                @method('PATCH')
                @include('layouts.partials.modal-header', ['title' => 'Renombrar carpeta'])
                <label class="label">
                    <span>Nombre</span>
                    <input type="text" name="name" required maxlength="120" class="input mt-1"
                           value="{{ $currentFolder->name }}">
                </label>
                <label class="label">
                    <span>Icono</span>
                    <div class="mt-1">@include('notes.partials.icon-field', ['value' => $currentFolder->icon])</div>
                </label>
                <div class="modal-footer flex justify-end gap-2">
                    <button type="button" class="btn-ghost" data-modal-close>Cancelar</button>
                    <button type="submit" class="btn">Guardar</button>
                </div>
            </form>
        </dialog>
    @endif
@endsection
