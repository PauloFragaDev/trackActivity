{{-- Mini-sidebar de la sección Configuración.

     Entradas dinámicas según ModuleVisibility:
       · "Etiquetas" solo aparece si Tareas está activado (no tiene sentido
         sin Kanban).
       · "Pomodoro" solo aparece si el módulo está activado.

     Resaltado activo: la primera entrada cuyo prefijo de ruta haga match.
     Mantenemos las URLs originales (projects.*, task-labels.*, etc.) — el
     hub solo cambia la NAVEGACIÓN, no rompe bookmarks. --}}
@php
    /** @var array $modules */
    $sections = [
        ['label' => 'General',    'route' => 'settings.general',     'match' => ['settings.general']],
        ['label' => 'Apariencia', 'route' => 'settings.appearance',  'match' => ['settings.appearance']],
        ['label' => 'Proyectos',  'route' => 'projects.index',       'match' => ['projects.*']],
    ];
    if ($modules['tasks']['enabled'] ?? true) {
        $sections[] = ['label' => 'Etiquetas', 'route' => 'task-labels.index', 'match' => ['task-labels.*']];
    }
    if ($modules['pomodoro']['enabled'] ?? true) {
        $sections[] = ['label' => 'Pomodoro',  'route' => 'settings.pomodoro', 'match' => ['settings.pomodoro*']];
    }
    $sections[] = ['label' => 'Exportar', 'route' => 'export.form', 'match' => ['export.*']];
    $sections[] = ['label' => 'Datos',    'route' => 'data.index',  'match' => ['data.*']];

    $itemClass = fn (array $matches) => request()->routeIs(...$matches)
        ? 'bg-ink-100 dark:bg-ink-800 text-ink-900 dark:text-ink-50 font-medium'
        : 'text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800';
@endphp

<aside class="settings-nav shrink-0 w-full lg:w-56 self-start">
    <div class="mb-3 px-2">
        <h2 class="text-[11px] uppercase tracking-wider text-muted font-semibold">Configuración</h2>
    </div>
    <nav class="space-y-0.5 text-sm">
        @foreach ($sections as $s)
            <a href="{{ route($s['route']) }}"
               class="block px-2 py-1.5 rounded {{ $itemClass($s['match']) }}">
                {{ $s['label'] }}
            </a>
        @endforeach
    </nav>
</aside>
