@extends('layouts.app')

@section('title', 'Export')

@section('content')
    <h1 class="text-xl font-semibold tracking-tight mb-6">Exportar timesheet</h1>

    <form method="POST" action="{{ url('/export') }}" class="card p-6 max-w-2xl space-y-5">
        @csrf

        <div class="grid grid-cols-2 gap-4">
            <label class="label">
                <span>Desde</span>
                <input type="date" name="from" value="{{ $weekAgo }}" required class="input">
            </label>
            <label class="label">
                <span>Hasta</span>
                <input type="date" name="to" value="{{ $today }}" required class="input">
            </label>
        </div>

        <fieldset>
            <legend class="text-xs uppercase tracking-wider text-muted mb-2">Proyectos (vacío = todos)</legend>
            <div class="flex flex-wrap gap-3">
                @foreach ($projects as $project)
                    <label class="surface-soft inline-flex items-center gap-2 px-3 py-1.5 rounded cursor-pointer hover:opacity-90">
                        <input type="checkbox" name="projects[]" value="{{ $project->code }}" class="accent-emerald-500">
                        <span class="inline-block w-2 h-2 rounded-full" style="background: {{ $project->color }}"></span>
                        <span class="text-sm">{{ $project->code }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="grid grid-cols-3 gap-4">
            <label class="label">
                <span>Confianza mínima</span>
                <select name="min_confidence" class="select">
                    <option value="low">Baja (incluye todas)</option>
                    <option value="medium">Media</option>
                    <option value="high">Alta</option>
                </select>
            </label>
            <label class="label">
                <span>Agrupar por</span>
                <select name="group_by" class="select">
                    <option value="session">Sesión</option>
                    <option value="project-day">Proyecto · día</option>
                </select>
            </label>
            <label class="label">
                <span>Formato</span>
                <select name="format" class="select">
                    <option value="txt">Texto plano (.txt)</option>
                    <option value="md">Markdown (.md)</option>
                    <option value="csv">CSV (.csv)</option>
                </select>
            </label>
        </div>

        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="include_idle" value="1" class="accent-emerald-500">
            Incluir bloques idle
        </label>

        <div class="pt-2 border-t divider">
            <button type="submit" class="btn">Descargar</button>
            <a href="{{ route('timeline.today') }}" class="btn-ghost ml-2">Cancelar</a>
        </div>
    </form>
@endsection
