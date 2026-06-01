@extends('layouts.app')
@section('title', 'Clientes')
@section('content')
    <div class="mb-5 flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold tracking-tight">Clientes</h1>
        <a href="{{ route('clients.create') }}" class="btn"><x-icon name="plus" class="w-4 h-4" /> Nuevo cliente</a>
    </div>

    @forelse ($clients as $c)
        @if ($loop->first)<div class="card divide-y divider">@endif
        <a href="{{ route('clients.show', $c) }}"
           class="flex items-center gap-3 px-4 py-3 hover:bg-ink-100 dark:hover:bg-ink-800">
            <span class="inline-block w-3 h-3 rounded-full shrink-0" style="background: {{ $c->color ?? '#94a3b8' }}"></span>
            <span class="flex-1 min-w-0">
                <span class="font-medium">{{ $c->name }}</span>
                @if ($c->company)<span class="text-muted text-sm"> · {{ $c->company }}</span>@endif
            </span>
            <span class="text-xs text-faint font-mono tabular-nums">{{ $c->projects_count }} proyectos</span>
        </a>
        @if ($loop->last)</div>@endif
    @empty
        <x-empty-state icon="users" title="Aún no tienes clientes"
            text="Crea un cliente y asígnale proyectos para ver su tiempo y actividad reunidos.">
            <a href="{{ route('clients.create') }}" class="btn"><x-icon name="plus" class="w-4 h-4" /> Nuevo cliente</a>
        </x-empty-state>
    @endforelse
@endsection
