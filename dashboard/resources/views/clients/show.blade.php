@extends('layouts.app')
@section('title', $client->name)
@section('content')
    <div class="mb-5 flex items-start justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <span class="inline-block w-3.5 h-3.5 rounded-full shrink-0" style="background: {{ $client->color ?? '#94a3b8' }}"></span>
            <div class="min-w-0">
                <h1 class="text-xl font-semibold tracking-tight truncate">{{ $client->name }}</h1>
                @if ($client->company)<p class="text-sm text-muted">{{ $client->company }}</p>@endif
            </div>
        </div>
        <a href="{{ route('clients.edit', $client) }}" class="btn-ghost text-sm"><x-icon name="edit" class="w-3.5 h-3.5" /> Editar</a>
    </div>

    @if ($client->email || $client->phone || $client->website)
        <div class="card p-4 mb-5 text-sm flex flex-wrap gap-x-6 gap-y-1">
            @if ($client->email)<a class="hover:underline" href="mailto:{{ $client->email }}">{{ $client->email }}</a>@endif
            @if ($client->phone)<span>{{ $client->phone }}</span>@endif
            @if ($client->website)<a class="hover:underline" href="{{ $client->website }}" target="_blank" rel="noopener">{{ $client->website }}</a>@endif
        </div>
    @endif

    @if ($client->notes)
        <div class="card p-4 mb-5 text-sm whitespace-pre-line">{{ $client->notes }}</div>
    @endif

    {{-- PR2 insertará aquí el bloque "Tiempo agregado". --}}

    <div class="grid gap-4 md:grid-cols-3 mb-6">
        <section class="card p-4 md:col-span-1">
            <h2 class="text-xs font-medium uppercase tracking-wider text-muted mb-3">Proyectos</h2>
            <div class="space-y-0.5">
                @forelse ($client->projects as $p)
                    <a href="{{ route('projects.edit', $p) }}" class="flex items-center gap-2 px-2 py-1.5 rounded text-sm hover:bg-ink-100 dark:hover:bg-ink-800">
                        <span class="inline-block w-2 h-2 rounded-full" style="background: {{ $p->color ?? '#94a3b8' }}"></span>
                        <span class="font-mono text-xs">{{ $p->code }}</span>
                        <span class="truncate">{{ $p->name }}</span>
                    </a>
                @empty
                    <p class="text-sm text-muted">Sin proyectos asignados aún.</p>
                @endforelse
            </div>
        </section>

        <section class="card p-4 md:col-span-2">
            <h2 class="text-xs font-medium uppercase tracking-wider text-muted mb-3">Tareas</h2>
            <div class="space-y-0.5">
                @forelse ($tasks as $t)
                    <div class="flex items-center gap-2 px-2 py-1.5 rounded text-sm">
                        <span class="flex-1 truncate">{{ $t->title }}</span>
                        <span class="chip">{{ $t->status->label() }}</span>
                    </div>
                @empty
                    <p class="text-sm text-muted">No hay tareas en los proyectos de este cliente.</p>
                @endforelse
            </div>
        </section>
    </div>

    <section class="card p-4">
        <h2 class="text-xs font-medium uppercase tracking-wider text-muted mb-3">Notas</h2>
        <div class="space-y-0.5">
            @forelse ($notes as $n)
                <a href="{{ route('notes.index', ['note' => $n->id]) }}" class="flex items-center gap-2 px-2 py-1.5 rounded text-sm hover:bg-ink-100 dark:hover:bg-ink-800">
                    <span>{{ $n->icon ?: '📄' }}</span>
                    <span class="flex-1 truncate">{{ $n->title }}</span>
                </a>
            @empty
                <p class="text-sm text-muted">No hay notas en los proyectos de este cliente.</p>
            @endforelse
        </div>
    </section>
@endsection
