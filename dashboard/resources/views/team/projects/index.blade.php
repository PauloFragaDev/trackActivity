@extends('layouts.settings')

@section('title', 'Proyectos del equipo')

@section('settings-content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">Proyectos del equipo</h1>
            <p class="text-sm text-muted mt-1">{{ $projects->count() }} {{ $projects->count() === 1 ? 'proyecto' : 'proyectos' }} definidos. Compartidos con todos los miembros.</p>
        </div>
        <a href="{{ route('team.projects.create') }}" class="btn">Nuevo proyecto</a>
    </div>

    @if ($projects->isEmpty())
        <div class="card p-10 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-ink-100 dark:bg-ink-800 text-ink-500 mb-3">
                <x-icon name="folder" class="w-6 h-6" />
            </div>
            <h3 class="text-base font-semibold mb-1">Aún no hay proyectos</h3>
            <p class="text-sm text-muted mb-4">Los proyectos del equipo están disponibles para todos los miembros al crear tareas.</p>
            <a href="{{ route('team.projects.create') }}" class="btn inline-flex items-center gap-1">
                <x-icon name="plus" class="w-4 h-4" /> Crear el primer proyecto
            </a>
        </div>
    @else
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="surface-soft text-xs uppercase tracking-wider text-muted">
                    <tr>
                        <th class="text-left px-4 py-3">Code</th>
                        <th class="text-left px-4 py-3">Nombre</th>
                        <th class="text-left px-4 py-3">Color</th>
                        <th class="text-right px-4 py-3">Tareas</th>
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
                            <td class="px-4 py-3 text-right font-mono">{{ $p->tasks_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('team.projects.edit', $p) }}" class="btn-ghost">Editar</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
