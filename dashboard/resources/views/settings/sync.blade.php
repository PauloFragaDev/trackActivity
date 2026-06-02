@extends('layouts.settings')

@section('title', 'Sincronización')

@section('settings-content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight">Sincronización</h1>
        <p class="text-sm text-muted mt-1">
            Activa o desactiva las sincronizaciones con sistemas externos.
        </p>
    </div>

    <form method="POST" action="{{ route('settings.sync.save') }}"
          class="card p-5 max-w-2xl space-y-4" data-loading-form>
        @csrf

        <div class="divide-y divide-ink-200 dark:divide-ink-700 -mx-2">
            <label class="flex items-start gap-3 px-2 py-3 cursor-pointer hover:bg-ink-50 dark:hover:bg-ink-800/40 rounded transition">
                <input type="checkbox" name="extension" value="1" @checked($extension)
                       class="mt-1 h-4 w-4 rounded border-ink-300 dark:border-ink-600 text-emerald-600 focus:ring-emerald-500">
                <span class="flex-1 min-w-0">
                    <span class="block text-sm font-medium">Extensión code-kanban</span>
                    <span class="block text-xs text-faint mt-0.5">
                        Sincronización bidireccional del tablero con la extensión de VSCode
                        (API <code class="chip">/api/sync/kanban</code>). Si la desactivas, la
                        extensión no podrá empujar ni recibir cambios.
                    </span>
                </span>
            </label>

            <label class="flex items-start gap-3 px-2 py-3 cursor-pointer hover:bg-ink-50 dark:hover:bg-ink-800/40 rounded transition">
                <input type="checkbox" name="crm" value="1" @checked($crm)
                       class="mt-1 h-4 w-4 rounded border-ink-300 dark:border-ink-600 text-emerald-600 focus:ring-emerald-500">
                <span class="flex-1 min-w-0">
                    <span class="block text-sm font-medium">CRM (Base44)</span>
                    <span class="block text-xs text-faint mt-0.5">
                        Importar clientes/proyectos/tareas desde el CRM de empresa. Se aplicará
                        cuando exista la API de Base44 (ahora mismo es solo una preferencia
                        guardada — la integración todavía no está construida).
                    </span>
                </span>
            </label>
        </div>

        <div class="pt-3 flex items-center justify-end border-t divider">
            <button type="submit" class="btn">Guardar</button>
        </div>
    </form>
@endsection
