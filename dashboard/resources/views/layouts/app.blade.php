<!DOCTYPE html>
<html lang="es" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'trackActivity')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    <header class="border-b border-ink-800 bg-ink-900/80 backdrop-blur sticky top-0 z-10">
        <div class="max-w-6xl mx-auto px-6 py-3 flex items-center justify-between">
            <a href="{{ route('timeline.today') }}" class="flex items-center gap-2 font-semibold tracking-tight">
                <span class="inline-block w-2 h-2 rounded-full bg-emerald-400"></span>
                trackActivity
            </a>
            <nav class="flex items-center gap-2 text-sm text-ink-300">
                <a class="btn-ghost" href="{{ route('timeline.today') }}">Hoy</a>
            </nav>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-6 py-8">
        @yield('content')
    </main>

    <footer class="max-w-6xl mx-auto px-6 py-6 text-xs text-ink-500">
        Local · {{ config('tracker.display_timezone') }} · BBDD: {{ basename(config('database.connections.sqlite.database')) }}
    </footer>
</body>
</html>
