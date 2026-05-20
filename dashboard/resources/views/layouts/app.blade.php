<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'trackActivity')</title>
    {{-- Aplicamos el tema antes de pintar para evitar flash --}}
    <script>
        (() => {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const initial = stored || (prefersDark ? 'dark' : 'light');
            document.documentElement.classList.toggle('dark', initial === 'dark');
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="surface-strong border-b divider sticky top-0 z-10">
        <div class="max-w-6xl mx-auto px-6 py-3 flex items-center justify-between">
            <a href="{{ route('timeline.today') }}" class="flex items-center gap-2 font-semibold tracking-tight">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                trackActivity
            </a>
            <nav class="flex items-center gap-1 text-sm">
                <a class="btn-ghost" href="{{ route('timeline.today') }}">Hoy</a>
                <a class="btn-ghost" href="{{ route('timeline.this_week') }}">Semana</a>
                <a class="btn-ghost" href="{{ route('calendar.current') }}">Calendario</a>
                <a class="btn-ghost" href="{{ route('projects.index') }}">Proyectos</a>
                <a class="btn-ghost" href="{{ route('notes.index') }}">Notas</a>
                <a class="btn-ghost" href="{{ route('export.form') }}">Export</a>
                <a class="btn-ghost" href="{{ route('help') }}">Ayuda</a>
                <button id="theme-toggle" type="button"
                        class="btn-ghost ml-2"
                        aria-label="Cambiar tema"
                        title="Cambiar tema">
                    <span data-icon-moon aria-hidden="true">☾</span>
                    <span data-icon-sun  aria-hidden="true" class="hidden">☀</span>
                </button>
            </nav>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-6 py-8">
        @if (session('status'))
            {{-- Lo recoge app.js y lo muestra como toast inferior --}}
            <div id="flash-data" data-message="{{ session('status') }}" hidden></div>
        @endif

        @if (session('overlap'))
            {{-- Aviso de solapamiento: app.js lo muestra con SweetAlert --}}
            <script type="application/json" id="overlap-data">@json(session('overlap'))</script>
        @endif

        @yield('content')
    </main>

    <footer class="max-w-6xl mx-auto px-6 py-6 text-xs text-muted">
        Local · {{ config('tracker.display_timezone') }} · BBDD: {{ basename(config('database.connections.sqlite.database')) }}
    </footer>
</body>
</html>
