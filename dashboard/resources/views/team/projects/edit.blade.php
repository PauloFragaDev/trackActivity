@extends('layouts.settings')

@section('title', $isNew ? 'Nuevo proyecto de equipo' : ('Editar · ' . $project->code))

@section('settings-content')
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-xl font-semibold tracking-tight">
            {{ $isNew ? 'Nuevo proyecto de equipo' : 'Editar ' . $project->code }}
        </h1>
        <a href="{{ route('team.projects.index') }}" class="btn-ghost">← Volver</a>
    </div>

    <form method="POST"
          action="{{ $isNew ? route('team.projects.store') : route('team.projects.update', $project) }}"
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
                       placeholder="FRONTEND"
                       class="input font-mono @error('code') is-invalid @enderror">
                <x-field-error name="code" />
            </label>
            <label class="label">
                <span>Nombre</span>
                <input type="text" name="name" required
                       value="{{ old('name', $project->name) }}"
                       maxlength="128"
                       placeholder="Frontend"
                       class="input @error('name') is-invalid @enderror">
                <x-field-error name="name" />
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
                           class="input font-mono @error('color') is-invalid @enderror"
                           id="color-text"
                           oninput="document.getElementById('color-picker').value = this.value">
                </div>
                <x-field-error name="color" />
            </label>
        </div>

        <label class="label">
            <span>Descripción (opcional)</span>
            <textarea name="description" rows="2" maxlength="1000"
                      class="input @error('description') is-invalid @enderror">{{ old('description', $project->description) }}</textarea>
            <x-field-error name="description" />
        </label>

        <div class="pt-2 border-t divider flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button type="submit" class="btn">
                    {{ $isNew ? 'Crear' : 'Guardar cambios' }}
                </button>
                <a href="{{ route('team.projects.index') }}" class="btn-ghost">Cancelar</a>
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
              action="{{ route('team.projects.destroy', $project) }}"
              class="hidden"
              data-confirm="¿Eliminar el proyecto {{ $project->code }}? Las tareas del equipo quedarán sin proyecto asignado.">
            @csrf
            @method('DELETE')
        </form>
    @endunless
@endsection
