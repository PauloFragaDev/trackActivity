{{--
    Nodo recursivo del árbol de carpetas en el menú lateral.
    Espera: $folder (NoteFolder), $depth (int) y, del scope padre,
    $sidebarFolders (todas las carpetas).
--}}
@php
    $depth  = $depth ?? 0;
    $active = request()->routeIs('notes.*') && (int) request()->query('folder') === $folder->id;
@endphp

<a href="{{ route('notes.index', ['folder' => $folder->id]) }}"
   class="block px-2 py-1.5 rounded text-sm truncate
          {{ $active
                ? 'bg-ink-100 dark:bg-ink-800 text-ink-900 dark:text-ink-50 font-medium'
                : 'text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800' }}"
   style="padding-left: {{ 0.5 + $depth * 0.85 }}rem"
   title="{{ $folder->name }}">
    {{ $folder->icon ?: '📁' }} {{ $folder->name }}
</a>

@foreach ($sidebarFolders->where('parent_id', $folder->id)->sortBy('name') as $child)
    @include('layouts.partials.sidebar-folder', ['folder' => $child, 'depth' => $depth + 1])
@endforeach
