<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- Identidad del usuario de esta instalación: el token (estable) permite al
         front distinguir los comentarios propios; el nombre es el visible. --}}
    <meta name="user-token" content="{{ \App\Services\UserIdentity::token() }}">
    <meta name="user-name" content="{{ \App\Services\UserIdentity::name() }}">
    <title>@yield('title', 'trackActivity')</title>

    {{-- Identidad de la app · favicon SVG, fallback ICO, apple-touch, manifest --}}
    <link rel="icon" type="image/svg+xml" href="{{ url('/icon.svg') }}">
    <link rel="alternate icon" href="{{ url('/favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ url('/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ url('/manifest.json') }}">
    {{-- Color del chrome del navegador: lo fija theme-color.js con el
         acento del tema activo (ver resources/js/theme-color.js). --}}
    <meta name="theme-color" content="#10b981">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="trackActivity">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    {{-- Estado de tema (modo + paleta) y sidebar antes de pintar.
         themeId vive en localStorage como mirror del Setting (lo que ve
         el servidor); si están desfasados ganan los datos del servidor
         que el composer inyecta en data-theme inline más abajo. --}}
    <script>
        (() => {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            document.documentElement.classList.toggle('dark', (stored || (prefersDark ? 'dark' : 'light')) === 'dark');
            const themeId = localStorage.getItem('themeId') || 'default';
            document.documentElement.setAttribute('data-theme', themeId);
            if (localStorage.getItem('sidebar') === 'collapsed') {
                document.documentElement.classList.add('sidebar-collapsed');
            }
            if (localStorage.getItem('notes-list') === 'collapsed') {
                document.documentElement.classList.add('notes-list-collapsed');
            }
        })();
    </script>
    {{-- Auto-sync del themeId del servidor → localStorage. Si el usuario
         cambió el tema desde otro navegador, la próxima carga aquí lo
         refleja sin esperar al primer cambio manual. --}}
    <script>
        (() => {
            const serverTheme = @json($themeId ?? 'default');
            if (localStorage.getItem('themeId') !== serverTheme) {
                localStorage.setItem('themeId', serverTheme);
                document.documentElement.setAttribute('data-theme', serverTheme);
            }
        })();
    </script>
    @if (($modules['team']['enabled'] ?? false) && config('team.supabase_url'))
    <script>
        window.SUPABASE_URL      = '{{ config("team.supabase_url") }}';
        window.SUPABASE_ANON_KEY = '{{ config("team.supabase_anon_key") }}';
        window.MY_MEMBER_ID      = {{ session('team_member_id') ? (int)session('team_member_id') : 'null' }};
    </script>
    @endif
    <script>window.TRANSLATIONS = @json(__('js'));</script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @php
        // Clases de un ítem de navegación según si su ruta está activa.
        $navItem = fn (array $routes) => request()->routeIs(...$routes)
            ? 'bg-[var(--selected)] text-ink-900 dark:text-ink-50 font-medium'
            : 'text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800';

        // Árbol de carpetas y notas favoritas (fijadas) para el menú lateral.
        $sidebarFolders = \App\Models\NoteFolder::orderBy('name')->get();
        $sidebarPinned  = \App\Models\Note::where('pinned', true)->orderBy('title')->get();
    @endphp

    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50
              focus:px-3 focus:py-2 focus:rounded focus:bg-ink-900 focus:text-white focus:shadow-lg">
        {{ __('nav.skip_to_content') }}
    </a>

    {{-- Botón hamburguesa (solo móvil) + overlay del drawer. --}}
    <button id="mobile-menu-btn" type="button"
            class="icon-btn bg-[var(--paper)] dark:bg-ink-900 border divider shadow"
            aria-label="{{ __('nav.open_menu') }}">
        <x-icon name="bars" class="w-5 h-5" />
    </button>
    <div id="mobile-sidebar-overlay" aria-hidden="true"></div>

    <div class="flex min-h-screen">
        {{-- ─────────────── Sidebar ─────────────── --}}
        <aside id="sidebar"
               class="w-56 shrink-0 flex flex-col sticky top-0 h-screen overflow-hidden
                      bg-[var(--surface-rail)] border-r divider">
            {{-- Cabecera: plegar/desplegar + marca --}}
            <div class="flex items-center gap-2 p-2 border-b divider">
                <button id="sidebar-toggle" type="button" class="btn-ghost shrink-0"
                        aria-label="{{ __('nav.collapse_sidebar') }}" title="{{ __('nav.collapse_sidebar') }}">
                    <span data-icon-collapse aria-hidden="true" class="inline-flex"><x-icon name="chevron-double-left" class="w-4 h-4" /></span>
                    <span data-icon-expand   aria-hidden="true" class="inline-flex"><x-icon name="chevron-double-right" class="w-4 h-4" /></span>
                </button>
                @if ($modules['team']['enabled'] ?? false)
                <button id="notif-bell-collapsed" type="button"
                        class="hidden btn-ghost shrink-0 relative"
                        aria-label="{{ __('nav.notifications') }}" title="{{ __('nav.notifications') }}">
                    <x-icon name="bell" class="w-4 h-4" />
                    <span id="notif-dot"
                          class="absolute top-0.5 right-0.5 w-2 h-2 rounded-full bg-red-500 ring-1 ring-white dark:ring-ink-900"></span>
                </button>
                @endif
                <a href="{{ route('dashboard') }}"
                   class="sidebar-full flex items-center gap-2 font-semibold tracking-tight whitespace-nowrap">
                    <img src="{{ url('/icon.svg') }}" alt="" class="w-5 h-5 rounded-md" aria-hidden="true">
                    trackActivity
                </a>
            </div>

            <nav class="sidebar-full flex-1 overflow-y-auto p-2 space-y-0.5 text-sm">
                {{-- Control del tracker (arranca/para el daemon Python) --}}
                <form method="POST" action="{{ route('tracker.toggle') }}" class="mb-1" data-loading-form>
                    @csrf
                    @php $running = $trackerRunning ?? false; @endphp
                    <button type="submit"
                            data-loading-label="{{ $running ? __('nav.tracker_stopping') : __('nav.tracker_starting') }}"
                            class="w-full flex items-center gap-2 px-2 py-1.5 rounded text-sm
                                   border divider hover:bg-ink-100 dark:hover:bg-ink-800
                                   disabled:opacity-60 disabled:cursor-wait"
                            aria-label="{{ $running ? __('nav.tracker_stop_aria') : __('nav.tracker_start_aria') }}">
                        <span class="inline-block w-2 h-2 rounded-full {{ $running ? 'bg-emerald-500 animate-pulse' : 'bg-ink-300 dark:bg-ink-700' }}"></span>
                        <span class="flex-1 text-left">{{ $running ? __('nav.tracker_active') : __('nav.tracker_inactive') }}</span>
                        <span class="text-xs text-muted">{{ $running ? __('nav.tracker_stop_text') : __('nav.tracker_start_text') }}</span>
                    </button>
                </form>

                {{-- Buscar (quick switcher) --}}
                <button type="button" data-qs-open
                        class="w-full flex items-center gap-1.5 px-2 py-1.5 rounded
                               text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800">
                    <x-icon name="search" class="w-4 h-4" />
                    <span>{{ __('common.search') }}</span>
                    <x-kbd class="ml-auto">Ctrl K</x-kbd>
                </button>

                {{-- Campana de notificaciones (solo si el módulo equipo está activo) --}}
                @if ($modules['team']['enabled'] ?? false)
                <div>
                    <button id="notif-bell-expanded" type="button"
                            class="w-full flex items-center gap-1.5 px-2 py-1.5 rounded
                                   text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800">
                        <x-icon name="bell" class="w-4 h-4 shrink-0" />
                        <span>{{ __('nav.notifications') }}</span>
                        <span id="notif-badge"
                              class="hidden ml-auto min-w-[1.1rem] h-[1.1rem] rounded-full
                                     bg-red-500 text-white text-[10px] font-bold
                                     flex items-center justify-center px-1 leading-none">0</span>
                    </button>

                    {{-- Panel inline: se expande dentro del sidebar --}}
                    <div id="notif-panel" class="hidden mt-0.5 ml-2">
                        <div class="flex items-center justify-between px-2 py-1">
                            <button id="notif-read-all" type="button"
                                    class="text-xs text-muted hover:text-ink-900 dark:hover:text-ink-100 ml-auto">
                                {{ __('nav.mark_all_read') }}
                            </button>
                        </div>
                        <ul id="notif-list" class="max-h-48 overflow-y-auto space-y-0.5">
                            <li class="px-2 py-3 text-xs text-muted text-center" data-empty>
                                {{ __('nav.no_notifications') }}
                            </li>
                        </ul>
                    </div>
                </div>
                @endif

                {{-- Inicio --}}
                <a href="{{ route('dashboard') }}"
                   class="block px-2 py-1.5 rounded {{ $navItem(['dashboard']) }}">{{ __('nav.dashboard') }}</a>

                {{-- Tracking. Oculto si el módulo está desactivado en ajustes generales. --}}
                @if ($modules['tracking']['enabled'] ?? true)
                <details class="group" @if (request()->routeIs('timeline.*', 'calendar.*', 'reports.*', 'insights.*')) open @endif>
                    <summary class="flex items-center gap-1.5 px-2 py-1.5 rounded cursor-pointer select-none list-none
                                    text-[11px] uppercase tracking-wider text-muted hover:bg-ink-100 dark:hover:bg-ink-800">
                        <span class="transition-transform group-open:rotate-90 inline-flex" aria-hidden="true"><x-icon name="chevron-right" class="w-2.5 h-2.5" /></span>
                        {{ __('nav.tracking') }}
                    </summary>
                    <div class="mt-0.5 ml-2 space-y-0.5">
                        <a href="{{ route('timeline.today') }}"
                           class="block px-2 py-1.5 rounded {{ $navItem(['timeline.today', 'timeline.day']) }}">{{ __('nav.today') }}</a>
                        <a href="{{ route('timeline.this_week') }}"
                           class="block px-2 py-1.5 rounded {{ $navItem(['timeline.this_week', 'timeline.week']) }}">{{ __('nav.week') }}</a>
                        @if ($modules['calendar']['enabled'] ?? true)
                            <a href="{{ route('calendar.current') }}"
                               class="block px-2 py-1.5 rounded {{ $navItem(['calendar.current', 'calendar.month']) }}">{{ __('nav.month') }}</a>
                        @endif
                        @if ($modules['reports']['enabled'] ?? true)
                            <a href="{{ route('reports.index') }}"
                               class="block px-2 py-1.5 rounded {{ $navItem(['reports.*']) }}">{{ __('nav.reports') }}</a>
                        @endif
                        @if ($modules['insights']['enabled'] ?? true)
                            <a href="{{ route('insights.index') }}"
                               class="block px-2 py-1.5 rounded {{ $navItem(['insights.*']) }}">{{ __('nav.insights') }}</a>
                        @endif
                    </div>
                </details>
                @endif

                {{-- Notas: grupo desplegable con el árbol de carpetas --}}
                @if ($modules['notes']['enabled'] ?? true)
                <details class="group" @if (request()->routeIs('notes.*')) open @endif>
                    <summary class="flex items-center gap-1.5 px-2 py-1.5 rounded cursor-pointer select-none list-none
                                    text-[11px] uppercase tracking-wider text-muted hover:bg-ink-100 dark:hover:bg-ink-800">
                        <span class="transition-transform group-open:rotate-90 inline-flex" aria-hidden="true"><x-icon name="chevron-right" class="w-2.5 h-2.5" /></span>
                        {{ __('nav.notes') }}
                    </summary>
                    <div class="mt-0.5 ml-2 space-y-0.5">
                        {{-- Favoritos: notas fijadas (★) --}}
                        @foreach ($sidebarPinned as $fav)
                            <a href="{{ route('notes.index', ['note' => $fav->id]) }}"
                               class="block px-2 py-1.5 rounded text-sm truncate
                                      {{ (int) request()->query('note') === $fav->id ? 'bg-[var(--selected)] text-ink-900 dark:text-ink-50 font-medium' : 'text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800' }}"
                               title="{{ $fav->title }}">
                                <x-icon name="star" class="w-3.5 h-3.5 inline-block align-text-bottom text-amber-500" /> {{ $fav->title }}
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
                            <x-icon name="trash" class="w-4 h-4 inline-block align-text-bottom" /> {{ __('nav.trash') }}
                        </a>
                        <button type="button" data-modal-open="#folder-new"
                                class="w-full text-left block px-2 py-1.5 rounded text-ink-600 dark:text-ink-300 hover:bg-ink-100 dark:hover:bg-ink-800">
                            {{ __('nav.new_folder') }}
                        </button>
                    </div>
                </details>
                @endif

                {{-- Tareas --}}
                @if ($modules['tasks']['enabled'] ?? true)
                    <a href="{{ route('tasks.index') }}"
                       class="block px-2 py-1.5 rounded {{ $navItem(['tasks.*']) }}">{{ __('nav.tasks') }}</a>
                @endif

                {{-- Pomodoro: timer independiente, una sola página. --}}
                @if ($modules['pomodoro']['enabled'] ?? true)
                    <a href="{{ route('pomodoro.index') }}"
                       class="block px-2 py-1.5 rounded {{ $navItem(['pomodoro.*']) }}">{{ __('nav.pomodoro') }}</a>
                @endif

                {{-- Configuración: una sola entrada. El layout `settings`
                     pinta un mini-sidebar con las subsecciones. --}}
                <a href="{{ route('settings.index') }}"
                   class="block px-2 py-1.5 rounded {{ $navItem(['settings.*', 'projects.*', 'task-labels.*', 'export.*', 'data.*']) }}">
                    {{ __('nav.settings') }}
                </a>

                {{-- Ayuda --}}
                <a href="{{ route('help') }}"
                   class="block px-2 py-1.5 rounded {{ $navItem(['help']) }}">{{ __('nav.help') }}</a>
            </nav>


            <div class="sidebar-full p-2 border-t divider">
                {{-- Temporizador minimizado: aparece aquí cuando se minimiza el
                     dock flotante; al clicar, vuelve a flotante. Lo gobierna
                     pomodoro.js (clase .pomodoro-sidebar--visible). --}}
                <button type="button" id="pomodoro-sidebar" data-pomodoro-sidebar
                        class="pomodoro-sidebar w-full items-center gap-2 px-2 py-1.5 rounded mb-1 text-sm hover:bg-ink-100 dark:hover:bg-ink-800"
                        aria-label="{{ __('pomodoro.sidebar_btn') }}"
                        title="{{ __('pomodoro.sidebar_tip') }}">
                    <span class="pomodoro-dock__dot" aria-hidden="true"></span>
                    <span class="text-[11px] uppercase tracking-wider text-muted" data-pomodoro-sidebar-phase>{{ __('pomodoro.phase_focus') }}</span>
                    <span class="font-mono tabular-nums ml-auto" data-pomodoro-sidebar-time>00:00</span>
                </button>
                <button id="theme-toggle" type="button"
                        class="btn-ghost w-full justify-start"
                        aria-label="{{ __('nav.theme_toggle') }}" title="{{ __('nav.theme_toggle') }}">
                    <span data-icon-moon aria-hidden="true" class="inline-flex"><x-icon name="moon" class="w-4 h-4" /></span>
                    <span data-icon-sun  aria-hidden="true" class="hidden"><x-icon name="sun"  class="w-4 h-4" /></span>
                    <span>{{ __('nav.theme_label') }}</span>
                </button>
            </div>
            {{-- Speech bubble para el estado colapsado (position: fixed, gestionado por JS) --}}
            @if ($modules['team']['enabled'] ?? false)
            <div id="notif-bubble"
                 class="notif-bubble hidden fixed z-[200] w-80
                        rounded-lg border divider shadow-xl
                        bg-[var(--paper)] dark:bg-ink-900">
                <div class="flex items-center justify-between px-3 py-2 border-b divider">
                    <span class="text-xs font-semibold uppercase tracking-wider text-muted">Notificaciones</span>
                    <button id="notif-bubble-read-all" type="button"
                            class="text-xs text-muted hover:text-ink-900 dark:hover:text-ink-100">
                        {{ __('nav.mark_all_read') }}
                    </button>
                </div>
                <ul id="notif-bubble-list" class="max-h-80 overflow-y-auto divide-y divide-ink-100 dark:divide-ink-800">
                    <li class="px-3 py-4 text-sm text-muted text-center" data-empty>
                        {{ __('nav.no_notifications') }}
                    </li>
                </ul>
            </div>
            @endif
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
        <input type="text" data-qs-input autocomplete="off" placeholder="{{ __('nav.search_ph') }}" class="input"
               role="combobox" aria-expanded="true" aria-controls="qs-results"
               aria-autocomplete="list" aria-label="{{ __('nav.search_aria') }}">
        <ul id="qs-results" data-qs-results role="listbox" aria-label="Resultados"
            class="mt-2 max-h-80 overflow-y-auto space-y-0.5"></ul>
    </dialog>

    {{-- Modal "Nueva carpeta": accesible desde el sidebar en cualquier página --}}
    <dialog id="folder-new" class="modal">
        <form method="POST" action="{{ route('note-folders.store') }}" class="space-y-3">
            @csrf
            @include('layouts.partials.modal-header', ['title' => __('nav.new_folder')])
            <label class="label">
                <span>{{ __('common.name') }}</span>
                <input type="text" name="name" required maxlength="120" class="input mt-1" placeholder="{{ __('nav.folder_name_ph') }}">
            </label>
            <label class="label">
                <span>{{ __('common.icon') }}</span>
                <div class="mt-1">@include('notes.partials.icon-field', ['value' => ''])</div>
            </label>
            <label class="label">
                <span>{{ __('nav.folder_parent') }}</span>
                <select name="parent_id" class="select mt-1">
                    <option value="">{{ __('notes.folder_root') }}</option>
                    @foreach ($sidebarFolders->sortBy('name') as $f)
                        <option value="{{ $f->id }}">{{ $f->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="modal-footer flex justify-end gap-2">
                <button type="button" class="btn-ghost" data-modal-close>{{ __('common.cancel') }}</button>
                <button type="submit" class="btn">{{ __('common.create') }}</button>
            </div>
        </form>
    </dialog>

    {{-- Dock flotante del Pomodoro: visible en cualquier página mientras
         haya una fase corriendo o esperando. El JS lo muestra/oculta con
         la clase .pomodoro-dock--visible. Lleva la config como data-attrs
         para poder pintar el contador sin necesidad de la página principal. --}}
    @if ($modules['pomodoro']['enabled'] ?? true)
        @php $pomCfg = app(\App\Services\PomodoroService::class)->currentConfig(); @endphp
        <a href="{{ route('pomodoro.index') }}"
           id="pomodoro-dock"
           class="pomodoro-dock"
           draggable="false"
           data-focus-min="{{ $pomCfg['pomodoro_focus_min'] }}"
           data-short-break-min="{{ $pomCfg['pomodoro_short_break_min'] }}"
           data-long-break-min="{{ $pomCfg['pomodoro_long_break_min'] }}"
           data-cycles-until-long="{{ $pomCfg['pomodoro_cycles_until_long'] }}"
           title="{{ __('pomodoro.dock_tip') }}">
            <span class="pomodoro-dock__dot" aria-hidden="true"></span>
            <span class="pomodoro-dock__phase text-[11px] uppercase tracking-wider" data-pomodoro-dock-phase>{{ __('pomodoro.phase_focus') }}</span>
            <span class="pomodoro-dock__time font-mono tabular-nums" data-pomodoro-dock-time>00:00</span>
            <button type="button" class="pomodoro-dock__min icon-btn"
                    data-pomodoro-dock-min aria-label="{{ __('pomodoro.minimize_tip') }}" title="{{ __('pomodoro.minimize_tip') }}">
                <x-icon name="minus" class="w-3.5 h-3.5" />
            </button>
            <button type="button" class="pomodoro-dock__pause icon-btn"
                    data-pomodoro-dock-pause aria-label="{{ __('nav.pause_resume') }}">·</button>
        </a>
    @endif

</body>
</html>
