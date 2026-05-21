<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
            ['folders', 'list'].forEach((p) => {
                if (localStorage.getItem('notes-' + p) === 'collapsed') {
                    document.documentElement.classList.add('notes-' + p + '-collapsed');
                }
            });
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

        // Árbol de carpetas de Notas para el menú lateral.
        $sidebarFolders = \App\Models\NoteFolder::orderBy('name')->get();
    @endphp

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
                <a href="{{ route('timeline.today') }}"
                   class="sidebar-full flex items-center gap-2 font-semibold tracking-tight whitespace-nowrap">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                    trackActivity
                </a>
            </div>

            <nav class="sidebar-full flex-1 overflow-y-auto p-2 space-y-0.5 text-sm">
                {{-- Tracking --}}
                <details class="group" @if (request()->routeIs('timeline.*', 'calendar.*')) open @endif>
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
                        @php
                            $notesRoot = request()->routeIs('notes.*')
                                && ! request()->query('folder') && ! request()->boolean('trash') && ! request()->query('q');
                        @endphp
                        <a href="{{ route('notes.index') }}"
                           class="block px-2 py-1.5 rounded {{ $notesRoot ? 'bg-ink-100 dark:bg-ink-800 text-ink-900 dark:text-ink-50 font-medium' : 'text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800' }}">
                            Todas las notas
                        </a>
                        @foreach ($sidebarFolders->whereNull('parent_id')->sortBy('name') as $folder)
                            @include('layouts.partials.sidebar-folder', ['folder' => $folder, 'depth' => 0])
                        @endforeach
                        <a href="{{ route('notes.index', ['trash' => 1]) }}"
                           class="block px-2 py-1.5 rounded {{ request()->boolean('trash') ? 'bg-ink-100 dark:bg-ink-800 text-ink-900 dark:text-ink-50 font-medium' : 'text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800' }}">
                            🗑 Papelera
                        </a>
                    </div>
                </details>

                {{-- Configuración --}}
                <details class="group" @if (request()->routeIs('projects.*', 'export.*')) open @endif>
                    <summary class="flex items-center gap-1.5 px-2 py-1.5 rounded cursor-pointer select-none list-none
                                    text-[11px] uppercase tracking-wider text-muted hover:bg-ink-100 dark:hover:bg-ink-800">
                        <span class="text-[9px] transition-transform group-open:rotate-90">▸</span>
                        Configuración
                    </summary>
                    <div class="mt-0.5 ml-2 space-y-0.5">
                        <a href="{{ route('projects.index') }}"
                           class="block px-2 py-1.5 rounded {{ $navItem(['projects.*']) }}">Proyectos</a>
                        <a href="{{ route('export.form') }}"
                           class="block px-2 py-1.5 rounded {{ $navItem(['export.*']) }}">Export</a>
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
            <main class="flex-1">
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
</body>
</html>
