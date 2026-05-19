@extends('layouts.app')

@section('title', $isNew ? 'Nuevo proyecto' : ('Editar · ' . $project->code))

@section('content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold tracking-tight">
            {{ $isNew ? 'Nuevo proyecto' : 'Editar ' . $project->code }}
        </h1>
        <a href="{{ route('projects.index') }}" class="btn-ghost">← Volver</a>
    </div>

    @if ($errors->any())
        <div class="card p-4 mb-4 border-rose-400/60 text-rose-700 dark:text-rose-300">
            <ul class="list-disc pl-5 space-y-0.5 text-sm">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST"
          action="{{ $isNew ? route('projects.store') : route('projects.update', $project) }}"
          class="card p-6 max-w-2xl space-y-5">
        @csrf
        @unless ($isNew)
            @method('PATCH')
        @endunless

        <div class="grid grid-cols-2 gap-4">
            <label class="label">
                <span>Code (mayúsculas)</span>
                <input type="text" name="code" required
                       value="{{ old('code', $project->code) }}"
                       pattern="[A-Z0-9_\-]+"
                       maxlength="32"
                       placeholder="JASPER"
                       class="input font-mono">
            </label>
            <label class="label">
                <span>Nombre</span>
                <input type="text" name="name" required
                       value="{{ old('name', $project->name) }}"
                       maxlength="128"
                       placeholder="Jasper"
                       class="input">
            </label>
        </div>

        <div class="grid grid-cols-2 gap-4 items-end">
            <label class="label">
                <span>Color (hex #RRGGBB)</span>
                <div class="flex items-center gap-2">
                    <input type="color" name="color"
                           value="{{ old('color', $project->color ?? '#10b981') }}"
                           class="h-10 w-12 rounded border divider bg-transparent cursor-pointer"
                           id="color-picker">
                    <input type="text" name="color_text" form="never"
                           value="{{ old('color', $project->color ?? '#10b981') }}"
                           class="input font-mono"
                           id="color-text"
                           oninput="document.getElementById('color-picker').value = this.value">
                </div>
            </label>
        </div>

        <label class="label">
            <span>Descripción (opcional)</span>
            <textarea name="description" rows="2" maxlength="1000"
                      class="input">{{ old('description', $project->description) }}</textarea>
        </label>

        <div class="pt-2 border-t divider flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button type="submit" class="btn">
                    {{ $isNew ? 'Crear' : 'Guardar cambios' }}
                </button>
                <a href="{{ route('projects.index') }}" class="btn-ghost">Cancelar</a>
            </div>
            @unless ($isNew)
                <button type="button"
                        class="btn-danger"
                        onclick="document.getElementById('delete-form').requestSubmit()">
                    Eliminar proyecto
                </button>
            @endunless
        </div>
    </form>

    @unless ($isNew)
        <form id="delete-form"
              method="POST"
              action="{{ route('projects.destroy', $project) }}"
              class="hidden"
              onsubmit="return confirm('¿Eliminar el proyecto {{ $project->code }}? Sus mappings se borrarán también. Los bloques quedan sin proyecto asignado.');">
            @csrf
            @method('DELETE')
        </form>

        {{-- ─────── Mappings ─────── --}}
        <section class="mt-8">
            <h2 class="text-base font-semibold mb-3">Mappings</h2>
            <p class="text-sm text-muted mb-4">
                Patrones que asocian la actividad del SO con este proyecto. El matching es
                <strong>substring case-insensitive</strong> salvo que marques "regex".
                Ver detalle en <a class="underline" href="{{ route('help') }}">la guía</a>.
            </p>

            {{-- Nuevo mapping --}}
            <form method="POST" action="{{ route('projects.mappings.store', $project) }}"
                  class="card p-4 mb-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    <label class="label md:col-span-3">
                        <span>Tipo</span>
                        <select name="type" class="select">
                            @foreach (\App\Models\ProjectMapping::TYPES as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="label md:col-span-5">
                        <span>Patrón</span>
                        <input type="text" name="pattern" required class="input font-mono"
                               placeholder="ej. jasper-api  |  github.com/company/jasper">
                    </label>
                    <label class="label md:col-span-2">
                        <span>Bonus</span>
                        <input type="number" name="weight_bonus" value="0" min="-10" max="10" class="input font-mono">
                    </label>
                    <label class="inline-flex items-center gap-2 md:col-span-1 text-sm">
                        <input type="checkbox" name="is_regex" value="1" class="accent-emerald-500">
                        regex
                    </label>
                    <div class="md:col-span-1 text-right">
                        <button type="submit" class="btn w-full md:w-auto">Añadir</button>
                    </div>
                </div>
            </form>

            @if ($mappings->isEmpty())
                <div class="card p-6 text-center text-muted">
                    Sin mappings. Añade al menos uno para que la actividad se atribuya a este proyecto.
                </div>
            @else
                <div class="card overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="surface-soft text-xs uppercase tracking-wider text-muted">
                            <tr>
                                <th class="text-left px-3 py-2">Tipo</th>
                                <th class="text-left px-3 py-2">Patrón</th>
                                <th class="text-center px-3 py-2">Regex</th>
                                <th class="text-right px-3 py-2">Bonus</th>
                                <th class="text-center px-3 py-2">Estado</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mappings as $m)
                                <tr class="border-t divider {{ $m->enabled ? '' : 'opacity-50' }}">
                                    <td class="px-3 py-2 font-mono text-xs">{{ $m->type }}</td>
                                    <td class="px-3 py-2 font-mono break-all">{{ $m->pattern }}</td>
                                    <td class="px-3 py-2 text-center">{{ $m->is_regex ? '✓' : '—' }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $m->weight_bonus > 0 ? '+' . $m->weight_bonus : $m->weight_bonus }}</td>
                                    <td class="px-3 py-2 text-center">
                                        <form method="POST" action="{{ route('projects.mappings.toggle', [$project, $m]) }}" class="inline">
                                            @csrf @method('PATCH')
                                            <button class="chip" title="Activar/desactivar">
                                                {{ $m->enabled ? 'activo' : 'inactivo' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <form method="POST" action="{{ route('projects.mappings.destroy', [$project, $m]) }}" class="inline"
                                              onsubmit="return confirm('¿Eliminar este mapping?');">
                                            @csrf @method('DELETE')
                                            <button class="btn-ghost text-rose-600 dark:text-rose-400">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endunless
@endsection
