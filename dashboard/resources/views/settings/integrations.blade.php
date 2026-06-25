@extends('layouts.settings')

@section('title', 'Integraciones')

@section('settings-content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight">Integraciones</h1>
        <p class="text-sm text-muted mt-1">
            Conexiones con servicios externos: Supabase (Kanban de equipo) y CRM Base44.
        </p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded px-4 py-2 text-sm bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-8 max-w-2xl">

        {{-- ── Supabase (Kanban de equipo) ── --}}
        <section class="card p-5">
            <h2 class="text-base font-semibold mb-1">Kanban de equipo (Supabase)</h2>
            <p class="text-sm text-muted mb-4">
                La conexión se configura en el fichero <code class="chip">.env</code> del servidor
                con las variables <code class="chip">SUPABASE_DB_*</code>.
            </p>

            <div class="flex items-center gap-2 mb-6">
                @if($supConnected)
                    <span class="inline-flex items-center gap-1.5 text-sm text-emerald-600 dark:text-emerald-400 font-medium">
                        Conectado
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-sm text-amber-600 dark:text-amber-400 font-medium">
                        Sin configurar
                    </span>
                    <span class="text-xs text-faint">
                        Añade <code class="chip">SUPABASE_DB_HOST</code> al .env para activar el Kanban de equipo.
                    </span>
                @endif
            </div>

            @if($supConnected)
                <h3 class="text-sm font-semibold mb-3">Miembros del equipo</h3>

                @if($members->isEmpty())
                    <p class="text-sm text-muted mb-3">No hay miembros. Añade el primero:</p>
                @else
                    <ul class="space-y-2 mb-4">
                        @foreach($members as $member)
                            <li class="flex items-center gap-3">
                                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white"
                                      style="background-color: {{ $member->color }}">
                                    {{ $member->initials() }}
                                </span>
                                <span class="flex-1 text-sm">{{ $member->name }}</span>
                                <form method="POST" action="{{ route('team.members.destroy', $member) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="text-xs text-rose-500 hover:text-rose-700 dark:hover:text-rose-400 transition">
                                        Eliminar
                                    </button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('team.members.store') }}" class="flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <label class="label" for="member-name">Nombre</label>
                        <input type="text" id="member-name" name="name" required maxlength="80"
                               class="input" placeholder="Ana García">
                    </div>
                    <div>
                        <label class="label" for="member-color">Color</label>
                        <input type="color" id="member-color" name="color" value="#6366f1"
                               class="h-9 w-12 rounded border border-ink-300 dark:border-ink-600 cursor-pointer">
                    </div>
                    <button type="submit" class="btn">Añadir</button>
                </form>
            @endif
        </section>

        <hr class="border-ink-200 dark:border-ink-700">

        {{-- ── Base44 CRM ── --}}
        <section class="card p-5">
            <h2 class="text-base font-semibold mb-1">CRM Base44</h2>
            <p class="text-sm text-muted mb-4">
                URL y token de la API REST del CRM. Cuando esté disponible, el comando
                <code class="chip">php artisan crm:sync</code> importará proyectos y tareas.
            </p>

            <form method="POST" action="{{ route('settings.integrations.save') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="base44-url">URL de la API</label>
                    <input type="url" id="base44-url" name="base44_url" maxlength="255"
                           value="{{ old('base44_url', $base44Url) }}"
                           class="input" placeholder="https://tu-app.base44.app/api">
                    @error('base44_url')
                        <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="label" for="base44-token">Token (Bearer)</label>
                    <input type="password" id="base44-token" name="base44_token" maxlength="500"
                           value="{{ old('base44_token', $base44Token) }}"
                           class="input" placeholder="Deja en blanco para no cambiar">
                    @error('base44_token')
                        <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                    @enderror
                </div>
                <div class="pt-1">
                    <button type="submit" class="btn">Guardar</button>
                </div>
            </form>
        </section>

    </div>
@endsection
