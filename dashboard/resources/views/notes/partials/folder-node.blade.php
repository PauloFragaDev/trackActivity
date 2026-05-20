{{--
    Nodo recursivo del árbol de carpetas. Espera: $folder (NoteFolder),
    $depth (int) y, del scope padre, $folders (todas) y $folderId (actual).
--}}
@php $depth = $depth ?? 0; @endphp

<a href="{{ route('notes.index', ['folder' => $folder->id]) }}"
   class="block px-2 py-1 rounded text-sm truncate
          {{ $folderId === $folder->id
                ? 'surface-soft font-medium'
                : 'text-muted hover:bg-ink-100 dark:hover:bg-ink-800' }}"
   style="padding-left: {{ 0.5 + $depth * 0.9 }}rem"
   title="{{ $folder->name }}">
    {{ $folder->name }}
</a>

@foreach ($folders->where('parent_id', $folder->id)->sortBy('name') as $child)
    @include('notes.partials.folder-node', ['folder' => $child, 'depth' => $depth + 1])
@endforeach
