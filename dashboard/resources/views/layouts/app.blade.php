<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'trackActivity')</title>
    {{-- Estado de tema y sidebar antes de pintar, para evitar parpadeo --}}
    <script>
        (() => {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', (stored || (prefersDark ? 'dark' : 'light')) === 'dark');
            if (localStorage.getItem('sidebar') === 'collapsed') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
            if (localStorage.getItem('notes-list') === 'collapsed') {
                document.documentElement.classList.add('notes-list-collapsed');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @php
        // Clases de un ítem de navegación según si su ruta está activa.
        $navItem = fn (array $routes) => request()->routeIs(...$routes)
            ? 'bg-ink-100 dark:bg-ink-800 text-ink-900 dark:text-ink-50 font-medium'
            : 'text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800';

        // Árbol de carpetas y notas favoritas (fijadas) para el menú lateral.
        $sidebarFolders = \App\Models\NoteFolder::orderBy('name')->get();
        $sidebarPinned  = \App\Models\Note::where('pinned', true)->orderBy('title')->get();
    @endphp

    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50
              focus:px-3 focus:py-2 focus:rounded focus:bg-ink-900 focus:text-white focus:shadow-lg">
        Saltar al contenido
    </a>

    {{-- Botón hamburguesa (solo móvil) + overlay del drawer. --}}
    <button id="mobile-menu-btn" type="button"
            class="icon-btn bg-white dark:bg-ink-900 border divider shadow"
            aria-label="Abrir menú">
        <x-icon name="bars" class="w-5 h-5" />
    </button>
    <div id="mobile-sidebar-overlay" aria-hidden="true"></div>

    <div class="flex min-h-screen">
        {{-- ─────────────── Sidebar ─────────────── --}}
        <aside id="sidebar"
               class="w-56 shrink-0 flex flex-col sticky top-0 h-screen overflow-hidden
                      bg-white dark:bg-ink-900 border-r divider">
            {{-- Cabecera: plegar/desplegar + marca --}}
            <div class="flex items-center gap-2 p-2 border-b divider">
                <button id="sidebar-toggle" type="button" class="btn-ghost shrink-0"
                        aria-label="Plegar o desplegar el menú" title="Plegar / desplegar menú">
                    <span data-icon-collapse aria-hidden="true">«</span>
                    <span data-icon-expand   aria-hidden="true">»</span>
                </button>
                <a href="{{ route('dashboard') }}"
                   class="sidebar-full flex items-center gap-2 font-semibold tracking-tight whitespace-nowrap">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                    trackActivity
                </a>
            </div>

            <nav class="sidebar-full flex-1 overflow-y-auto p-2 space-y-0.5 text-sm">
                {{-- Control del tracker (arranca/para el daemon Python) --}}
                <form method="POST" action="{{ route('tracker.toggle') }}" class="mb-1" data-loading-form>
                    @csrf
                    @php $running = $trackerRunning ?? false; @endphp
                    <button type="submit"
                            data-loading-label="{{ $running ? 'Deteniendo' : 'Iniciando' }}"
                            class="w-full flex items-center gap-2 px-2 py-1.5 rounded text-sm
                                   border divider hover:bg-ink-100 dark:hover:bg-ink-800
                                   disabled:opacity-60 disabled:cursor-wait"
                            aria-label="{{ $running ? 'Detener tracker' : 'Iniciar tracker' }}">
                        <span class="inline-block w-2 h-2 rounded-full {{ $running ? 'bg-emerald-500 animate-pulse' : 'bg-ink-300 dark:bg-ink-700' }}"></span>
                        <span class="flex-1 text-left">{{ $running ? 'Tracker activo' : 'Tracker detenido' }}</span>
                        <span class="text-xs text-muted">{{ $running ? 'parar' : 'iniciar' }}</span>
                    </button>
                </form>

                {{-- Buscar (quick switcher) --}}
                <button type="button" data-qs-open
                        class="w-full flex items-center gap-1.5 px-2 py-1.5 rounded
                               text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800">
                    <span aria-hidden="true">🔍</span>
                    <span>Buscar</span>
                    <span class="ml-auto text-[10px] text-faint">Ctrl K</span>
                </button>

                {{-- Inicio --}}
                <a href="{{ route('dashboard') }}"
                   class="block px-2 py-1.5 rounded {{ $navItem(['dashboard']) }}">Inicio</a>

                {{-- Tracking --}}
                <details class="group" @if (request()->routeIs('timeline.*', 'calendar.*', 'reports.*')) open @endif>
                    <summary class="flex items-center gap-1.5 px-2 py-1.5 rounded cursor-pointer select-none list-none
                                    text-[11px] uppercase tracking-wider text-muted hover:bg-ink-100 dark:hover:bg-ink-800">
                        <span class="text-[9px] transition-transform group-open:rotate-90">▸</span>
                        Tracking
                    </summary>
                    <div class="mt-0.5 ml-2 space-y-0.5">
                        <a href="{{ route('timeline.today') }}"
                           class="block px-2 py-1.5 rounded {{ $navItem(['timeline.today', 'timeline.day']) }}">Hoy</a>
                        <a href="{{ route('timeline.this_week') }}"
                           class="block px-2 py-1.5 rounded {{ $navItem(['timeline.this_week', 'timeline.week']) }}">Semana</a>
                        <a href="{{ route('calendar.current') }}"
                           class="block px-2 py-1.5 rounded {{ $navItem(['calendar.current', 'calendar.month']) }}">Mes</a>
                        <a href="{{ route('reports.index') }}"
                           class="block px-2 py-1.5 rounded {{ $navItem(['reports.*']) }}">Informes</a>
                    </div>
                </details>

                {{-- Notas: grupo desplegable con el árbol de carpetas --}}
                <details class="group" @if (request()->routeIs('notes.*')) open @endif>
                    <summary class="flex items-center gap-1.5 px-2 py-1.5 rounded cursor-pointer select-none list-none
                                    text-[11px] uppercase tracking-wider text-muted hover:bg-ink-100 dark:hover:bg-ink-800">
                        <span class="text-[9px] transition-transform group-open:rotate-90">▸</span>
                        Notas
                    </summary>
                    <div class="mt-0.5 ml-2 space-y-0.5">
                        {{-- Favoritos: notas fijadas (★) --}}
                        @foreach ($sidebarPinned as $fav)
                            <a href="{{ route('notes.index', ['note' => $fav->id]) }}"
                               class="block px-2 py-1.5 rounded text-sm truncate
                                      {{ (int) request()->query('note') === $fav->id ? 'bg-ink-100 dark:bg-ink-800 text-ink-900 dark:text-ink-50 font-medium' : 'text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800' }}"
                               title="{{ $fav->title }}">
                                <span class="text-amber-500">★</span> {{ $fav->title }}
                            </a>
                        @endforeach
                        @if ($sidebarPinned->isNotEmpty())
                            <div class="my-1 border-t divider"></div>
                        @endif

                        {{-- Árbol de carpetas --}}
                        @foreach ($sidebarFolders->whereNull('parent_id')->sortBy('name') as $folder)
                            @include('layouts.partials.sidebar-folder', ['folder' => $folder, 'depth' => 0])
                        @endforeach

                        <a href="{{ route('notes.index', ['trash' => 1]) }}"
                           class="block px-2 py-1.5 rounded {{ request()->boolean('trash') ? 'bg-ink-100 dark:bg-ink-800 text-ink-900 dark:text-ink-50 font-medium' : 'text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800' }}">
                            🗑 Papelera
                        </a>
                        <button type="button" data-modal-open="#folder-new"
                                class="w-full text-left block px-2 py-1.5 rounded text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800">
                            + Nueva carpeta
                        </button>
                    </div>
                </details>

                {{-- Tareas --}}
                <a href="{{ route('tasks.index') }}"
                   class="block px-2 py-1.5 rounded {{ $navItem(['tasks.*']) }}">Tareas</a>

                {{-- Configuración --}}
                <details class="group" @if (request()->routeIs('projects.*', 'export.*', 'data.*', 'task-labels.*', 'settings.*')) open @endif>
                    <summary class="flex items-center gap-1.5 px-2 py-1.5 rounded cursor-pointer select-none list-none
                                    text-[11px] uppercase tracking-wider text-muted hover:bg-ink-100 dark:hover:bg-ink-800">
                        <span class="text-[9px] transition-transform group-open:rotate-90">▸</span>
                        Configuración
                    </summary>
                    <div class="mt-0.5 ml-2 space-y-0.5">
                        <a href="{{ route('projects.index') }}"
                           class="block px-2 py-1.5 rounded {{ $navItem(['projects.*']) }}">Proyectos</a>
                        <a href="{{ route('task-labels.index') }}"
                           class="block px-2 py-1.5 rounded {{ $navItem(['task-labels.*']) }}">Etiquetas</a>
                        <a href="{{ route('settings.pomodoro') }}"
                           class="block px-2 py-1.5 rounded {{ $navItem(['settings.*']) }}">Pomodoro</a>
                        <a href="{{ route('export.form') }}"
                           class="block px-2 py-1.5 rounded {{ $navItem(['export.*']) }}">Export</a>
                        <a href="{{ route('data.index') }}"
                           class="block px-2 py-1.5 rounded {{ $navItem(['data.*']) }}">Datos</a>
                    </div>
                </details>

                {{-- Ayuda --}}
                <a href="{{ route('help') }}"
                   class="block px-2 py-1.5 rounded {{ $navItem(['help']) }}">Ayuda</a>
            </nav>

            <div class="sidebar-full p-2 border-t divider">
                <button id="theme-toggle" type="button"
                        class="btn-ghost w-full justify-start"
                        aria-label="Cambiar tema" title="Cambiar tema">
                    <span data-icon-moon aria-hidden="true">☾</span>
                    <span data-icon-sun  aria-hidden="true" class="hidden">☀</span>
                    <span>Tema</span>
                </button>
            </div>
        </aside>

        {{-- ─────────────── Contenido ─────────────── --}}
        <div class="flex-1 min-w-0 flex flex-col">
            <main id="main-content" tabindex="-1" class="flex-1 scroll-mt-4">
                <div class="@yield('container', 'max-w-6xl mx-auto') px-6 py-8">
                    @if (session('status'))
                        {{-- Lo recoge app.js y lo muestra como toast inferior --}}
                        <div id="flash-data" data-message="{{ session('status') }}" hidden></div>
                    @endif

                    @if (session('overlap'))
                        {{-- Aviso de solapamiento: app.js lo muestra con SweetAlert --}}
                        <script type="application/json" id="overlap-data">@json(session('overlap'))</script>
                    @endif

                    @yield('content')
                </div>
            </main>

            <footer class="px-6 py-4 text-xs text-muted border-t divider">
                Local · {{ config('tracker.display_timezone') }} · BBDD: {{ basename(config('database.connections.sqlite.database')) }}
            </footer>
        </div>
    </div>

    {{-- Quick switcher (Ctrl/Cmd+K): buscar y saltar a una nota --}}
    <dialog id="quick-switcher" class="modal" aria-label="Buscar nota">
        <input type="text" data-qs-input autocomplete="off" placeholder="Buscar o ir a…" class="input"
               role="combobox" aria-expanded="true" aria-controls="qs-results"
               aria-autocomplete="list" aria-label="Buscar o ir a una sección">
        <ul id="qs-results" data-qs-results role="listbox" aria-label="Resultados"
            class="mt-2 max-h-80 overflow-y-auto space-y-0.5"></ul>
    </dialog>

    {{-- Modal "Nueva carpeta": accesible desde el sidebar en cualquier página --}}
    <dialog id="folder-new" class="modal">
        <form method="POST" action="{{ route('note-folders.store') }}" class="space-y-3">
            @csrf
            @include('layouts.partials.modal-header', ['title' => 'Nueva carpeta'])
            <label class="label">
                <span>Nombre</span>
                <input type="text" name="name" required maxlength="120" class="input mt-1" placeholder="Ideas, Trabajo…">
            </label>
            <label class="label">
                <span>Icono</span>
                <div class="mt-1">@include('notes.partials.icon-field', ['value' => ''])</div>
            </label>
            <label class="label">
                <span>Dentro de</span>
                <select name="parent_id" class="select mt-1">
                    <option value="">— Carpeta raíz —</option>
                    @foreach ($sidebarFolders->sortBy('name') as $f)
                        <option value="{{ $f->id }}">{{ $f->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" class="btn-ghost" data-modal-close>Cancelar</button>
                <button type="submit" class="btn">Crear</button>
            </div>
        </form>
    </dialog>

    {{-- Pill flotante del cronómetro (visible en cualquier página). El módulo
         pomodoro.js cablea estados, ticker, transiciones y modal de cierre. --}}
    @if (! empty($activeTimer))
        @php
            $tp = $activeTimer;
            $cfg = app(\App\Services\PomodoroService::class)->currentConfig();
            $phaseSec = match ($tp->state) {
                \App\Models\ActiveTimer::STATE_SHORT_BREAK => $cfg['pomodoro_short_break_min'] * 60,
                \App\Models\ActiveTimer::STATE_LONG_BREAK  => $cfg['pomodoro_long_break_min'] * 60,
                default                                     => $cfg['pomodoro_focus_min'] * 60,
            };
            $stateLabel = match ($tp->state) {
                \App\Models\ActiveTimer::STATE_SHORT_BREAK => 'Pausa corta',
                \App\Models\ActiveTimer::STATE_LONG_BREAK  => 'Pausa larga',
                default                                     => 'Foco',
            };
        @endphp
        <div id="timer-pill"
             data-state="{{ $tp->state }}"
             data-phase-started-at="{{ $tp->phase_started_at?->toIso8601String() ?? $tp->starts_at->toIso8601String() }}"
             data-paused-at="{{ $tp->paused_at?->toIso8601String() }}"
             data-paused-offset-seconds="{{ $tp->paused_offset_seconds }}"
             data-phase-duration-seconds="{{ $phaseSec }}"
             data-cycle-count="{{ $tp->cycle_count }}"
             data-task-id="{{ $tp->task_id }}"
             data-task-title="{{ $tp->task?->title ?? 'Sin tarea' }}"
             class="fixed bottom-4 left-1/2 -translate-x-1/2 z-40
                    card shadow-2xl pl-3 pr-2 py-2 flex items-center gap-2 text-sm
                    timer-pill timer-pill--{{ str_replace('_', '-', $tp->state) }} {{ $tp->paused_at ? 'timer-pill--paused' : '' }}">
            <span class="timer-pill__dot inline-block w-2 h-2 rounded-full" data-timer-dot></span>
            <span class="text-[11px] uppercase tracking-wider text-muted hidden sm:inline" data-timer-state-label>{{ $stateLabel }}</span>
            <span class="font-medium max-w-[16rem] truncate" data-timer-task-title>{{ $tp->task?->title ?? 'Sin tarea' }}</span>
            <span class="font-mono text-faint tabular-nums" data-timer-elapsed>00:00</span>
            <span class="text-[10px] text-faint" title="Ciclos de foco completados" data-timer-cycles>#{{ $tp->cycle_count }}</span>

            <div class="flex items-center gap-0.5 ml-1 border-l divider pl-1">
                <button type="button" class="icon-btn" data-timer-toggle-pause
                        aria-label="Pausar / reanudar" title="Pausar / reanudar">
                    <span data-timer-icon-pause class="{{ $tp->paused_at ? 'hidden' : '' }}">⏸</span>
                    <span data-timer-icon-play class="{{ $tp->paused_at ? '' : 'hidden' }}">▶</span>
                </button>
                <button type="button" class="icon-btn text-faint" data-timer-skip
                        aria-label="Saltar a la siguiente fase" title="Saltar fase">⏭</button>
                <button type="button" class="icon-btn text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-900/30"
                        data-timer-stop aria-label="Parar cronómetro" title="Parar cronómetro">
                    <x-icon name="close" class="w-3.5 h-3.5" />
                </button>
            </div>
        </div>
    @endif

    {{-- Modal de cierre del focus (rellena mood/progress/nota). Se abre desde JS. --}}
    <dialog id="focus-close-modal" class="modal" aria-labelledby="focus-close-title">
        <form id="focus-close-form" class="space-y-3" novalidate>
            <h2 id="focus-close-title" class="text-base font-semibold">¿Cómo fue ese foco?</h2>
            <p class="text-xs text-faint" data-focus-summary></p>

            <div>
                <span class="text-sm font-medium">Mood</span>
                <div class="mt-1 flex items-center gap-1" data-focus-mood role="radiogroup" aria-label="Mood">
                    @foreach (['😣' => 1, '🙁' => 2, '😐' => 3, '🙂' => 4, '😄' => 5] as $emoji => $v)
                        <button type="button" class="icon-btn text-lg" data-mood-value="{{ $v }}" aria-label="Mood {{ $v }}">{{ $emoji }}</button>
                    @endforeach
                </div>
            </div>

            <div>
                <span class="text-sm font-medium">¿Avanzaste?</span>
                <div class="mt-1 flex flex-wrap gap-1" data-focus-progress role="radiogroup" aria-label="Progreso">
                    <button type="button" class="btn-ghost text-xs" data-progress-value="yes">Sí</button>
                    <button type="button" class="btn-ghost text-xs" data-progress-value="partial">A medias</button>
                    <button type="button" class="btn-ghost text-xs" data-progress-value="no">No</button>
                </div>
            </div>

            <label class="block">
                <span class="text-sm font-medium">Nota</span>
                <textarea name="notes" rows="2" maxlength="2000"
                          class="input mt-1 w-full resize-y" placeholder="Opcional · qué hiciste, qué bloqueó, etc."></textarea>
            </label>

            <div class="flex justify-between items-center pt-1">
                <button type="button" class="btn-ghost text-xs" data-focus-skip>Saltar</button>
                <button type="submit" class="btn">Guardar y continuar</button>
            </div>
        </form>
    </dialog>

</body>
</html>
