@extends('layouts.app')

@section('title', 'Export')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight mb-6">Exportar timesheet</h1>

    <form method="POST" action="{{ url('/export') }}" class="card p-6 max-w-2xl space-y-5">
        @csrf

        <div class="grid grid-cols-2 gap-4">
            <label class="block">
                <span class="text-xs uppercase tracking-wider text-ink-500">Desde</span>
                <input type="date" name="from" value="{{ $weekAgo }}" required
                       class="mt-1 w-full bg-ink-800 border border-ink-700 rounded px-3 py-2 text-sm text-ink-100">
            </label>

            <label class="block">
                <span class="text-xs uppercase tracking-wider text-ink-500">Hasta</span>
                <input type="date" name="to" value="{{ $today }}" required
                       class="mt-1 w-full bg-ink-800 border border-ink-700 rounded px-3 py-2 text-sm text-ink-100">
            </label>
        </div>

        <fieldset>
            <legend class="text-xs uppercase tracking-wider text-ink-500 mb-2">Proyectos (vacío = todos)</legend>
            <div class="flex flex-wrap gap-3">
                @foreach ($projects as $project)
                    <label class="inline-flex items-center gap-2 px-3 py-1.5 rounded bg-ink-800 cursor-pointer hover:bg-ink-700">
                        <input type="checkbox" name="projects[]" value="{{ $project->code }}"
                               class="accent-emerald-400">
                        <span class="inline-block w-2 h-2 rounded-full" style="background: {{ $project->color }}"></span>
                        <span class="text-sm">{{ $project->code }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="grid grid-cols-3 gap-4">
            <label class="block">
                <span class="text-xs uppercase tracking-wider text-ink-500">Confianza mínima</span>
                <select name="min_confidence"
                        class="mt-1 w-full bg-ink-800 border border-ink-700 rounded px-3 py-2 text-sm text-ink-100">
                    <option value="low">Baja (incluye todas)</option>
                    <option value="medium">Media</option>
                    <option value="high">Alta</option>
                </select>
            </label>

            <label class="block">
                <span class="text-xs uppercase tracking-wider text-ink-500">Agrupar por</span>
                <select name="group_by"
                        class="mt-1 w-full bg-ink-800 border border-ink-700 rounded px-3 py-2 text-sm text-ink-100">
                    <option value="session">Sesión</option>
                    <option value="project-day">Proyecto · día</option>
                </select>
            </label>

            <label class="block">
                <span class="text-xs uppercase tracking-wider text-ink-500">Formato</span>
                <select name="format"
                        class="mt-1 w-full bg-ink-800 border border-ink-700 rounded px-3 py-2 text-sm text-ink-100">
                    <option value="txt">Texto plano (.txt)</option>
                    <option value="md">Markdown (.md)</option>
                    <option value="csv">CSV (.csv)</option>
                </select>
            </label>
        </div>

        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="include_idle" value="1" class="accent-emerald-400">
            Incluir bloques idle
        </label>

        <div class="pt-2 border-t border-ink-800">
            <button type="submit" class="btn">Descargar</button>
            <a href="{{ route('timeline.today') }}" class="btn-ghost ml-2">Cancelar</a>
        </div>
    </form>
@endsection
