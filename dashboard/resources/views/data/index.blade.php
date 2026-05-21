@extends('layouts.app')

@section('title', 'Datos')

@section('content')
    @php
        $human = fn (int $b) => $b >= 1048576
            ? round($b / 1048576, 1) . ' MB'
            : max(1, (int) round($b / 1024)) . ' KB';
        $tz = config('tracker.display_timezone', 'UTC');
    @endphp

    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight">Datos</h1>
        <p class="text-sm text-muted mt-1">Copias de seguridad y exportación.</p>
    </div>

    @if ($errors->any())
        <div id="form-errors" class="card p-4 mb-4 border-rose-400/60 text-rose-700 dark:text-rose-300">
            <ul class="list-disc pl-5 space-y-0.5 text-sm">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- ─── Copias de seguridad ─── --}}
    <section class="card p-5 mb-5">
        <div class="flex items-center justify-between gap-3 mb-1">
            <h2 class="text-base font-semibold">Copias de seguridad</h2>
            <form method="POST" action="{{ route('data.backup') }}">
                @csrf
                <button type="submit" class="btn text-sm">Crear copia ahora</button>
            </form>
        </div>
        <p class="text-sm text-muted mb-4">
            Se crea una copia automática a diario; se conservan las 14 más recientes.
        </p>

        @if (empty($snapshots))
            <p class="text-sm text-muted">Todavía no hay copias.</p>
        @else
            <div class="divide-y divider">
                @foreach ($snapshots as $s)
                    <div class="flex items-center justify-between gap-3 py-2">
                        <div class="min-w-0">
                            <div class="text-sm font-medium truncate">{{ $s['name'] }}</div>
                            <div class="text-xs text-faint">
                                {{ \Carbon\CarbonImmutable::createFromTimestamp($s['mtime'], $tz)->locale('es')->isoFormat('D MMM YYYY, HH:mm') }}
                                · {{ $human($s['size']) }}
                            </div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            <a href="{{ route('data.backup.download', $s['name']) }}" class="btn-ghost text-xs">Descargar</a>
                            <form method="POST" action="{{ route('data.restore') }}"
                                  data-confirm="¿Restaurar la base de datos desde «{{ $s['name'] }}»? Se sobrescribirá la BBDD actual — se guarda una copia previa automáticamente."
                                  data-confirm-button="Sí, restaurar">
                                @csrf
                                <input type="hidden" name="snapshot" value="{{ $s['name'] }}">
                                <button type="submit" class="btn-ghost text-xs text-rose-600 dark:text-rose-400">Restaurar</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('data.restore') }}" enctype="multipart/form-data"
              class="mt-4 pt-4 border-t divider flex items-center gap-2 flex-wrap"
              data-confirm="¿Restaurar la base de datos desde el archivo elegido? Se sobrescribirá la BBDD actual — se guarda una copia previa automáticamente."
              data-confirm-button="Sí, restaurar">
            @csrf
            <span class="text-sm text-muted">Restaurar desde un archivo:</span>
            <input type="file" name="file" accept=".db,.sqlite" required class="text-sm">
            <button type="submit" class="btn-ghost text-sm">Restaurar</button>
        </form>
    </section>
@endsection
