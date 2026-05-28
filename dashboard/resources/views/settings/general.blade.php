@extends('layouts.settings')

@section('title', 'Ajustes generales')

@section('settings-content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight">Ajustes generales</h1>
        <p class="text-sm text-muted mt-1">
            Activa o desactiva los módulos opcionales. Los que ocultes
            desaparecen del sidebar global; sus rutas siguen vivas para
            no romper bookmarks.
        </p>
    </div>

    <form method="POST" action="{{ route('settings.general.save') }}"
          class="card p-5 max-w-2xl space-y-4" data-loading-form>
        @csrf

        <div class="space-y-3">
            <div>
                <h2 class="text-sm font-semibold">Módulos visibles</h2>
                <p class="text-xs text-faint mt-0.5">
                    El Tracker (Inicio · Tracking) es núcleo y no se puede desactivar.
                </p>
            </div>

            <div class="divide-y divide-ink-200 dark:divide-ink-700 -mx-2">
                @foreach ($modules as $slug => $m)
                    <label class="flex items-start gap-3 px-2 py-3 cursor-pointer hover:bg-ink-50 dark:hover:bg-ink-800/40 rounded transition">
                        <input type="checkbox"
                               name="modules[{{ $slug }}]"
                               value="1"
                               @checked($m['enabled'])
                               class="mt-1 h-4 w-4 rounded border-ink-300 dark:border-ink-600
                                      text-emerald-600 focus:ring-emerald-500">
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-medium">{{ $m['label'] }}</span>
                            <span class="block text-xs text-faint mt-0.5">{{ $m['description'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="pt-3 flex items-center justify-between border-t divider">
            <p class="text-xs text-faint">
                Los cambios se aplican al siguiente refresco de cualquier página.
            </p>
            <button type="submit" class="btn">Guardar</button>
        </div>
    </form>
@endsection
