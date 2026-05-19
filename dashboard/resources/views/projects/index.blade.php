@extends('layouts.app')

@section('title', 'Proyectos')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Proyectos</h1>
            <p class="text-sm text-muted mt-1">{{ $projects->count() }} proyectos definidos.</p>
        </div>
        <a href="{{ route('projects.create') }}" class="btn">Nuevo proyecto</a>
    </div>

    @if ($projects->isEmpty())
        <div class="card p-8 text-center text-muted">
            <p>Aún no hay proyectos. Crea el primero para empezar a clasificar tu actividad.</p>
        </div>
    @else
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="surface-soft text-xs uppercase tracking-wider text-muted">
                    <tr>
                        <th class="text-left px-4 py-3">Code</th>
                        <th class="text-left px-4 py-3">Nombre</th>
                        <th class="text-left px-4 py-3">Color</th>
                        <th class="text-right px-4 py-3">Mappings</th>
                        <th class="text-right px-4 py-3">Bloques</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($projects as $p)
                        <tr class="border-t divider">
                            <td class="px-4 py-3 font-mono">{{ $p->code }}</td>
                            <td class="px-4 py-3">{{ $p->name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-2">
                                    <span class="inline-block w-3 h-3 rounded" style="background: {{ $p->color ?? '#777' }}"></span>
                                    <span class="font-mono text-xs text-muted">{{ $p->color ?? '—' }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{{ $p->mappings_count }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $p->time_blocks_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('projects.edit', $p) }}" class="btn-ghost">Editar</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
