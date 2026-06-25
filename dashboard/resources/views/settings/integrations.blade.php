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

            <div class="flex items-center justify-between mb-6">
                <div>
                    <p class="text-sm font-medium">Integración de equipo activa</p>
                    <p class="text-xs text-faint mt-0.5">Desactívala si no usas Supabase en esta instalación.</p>
                </div>
                <form method="POST" action="{{ route('settings.integrations.save') }}" id="team-enabled-form">
                    @csrf
                    <input type="hidden" name="team_enabled" value="0">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="team_enabled" value="1"
                               {{ $teamEnabled ? 'checked' : '' }}
                               onchange="document.getElementById('team-enabled-form').submit()"
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-ink-300 dark:bg-ink-600 rounded-full peer peer-checked:bg-primary transition-colors"></div>
                        <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                    </label>
                </form>
            </div>

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
                <p class="text-xs text-faint mb-3">Los miembros se gestionan directamente en Supabase (tabla <code class="chip">team_members</code>).</p>

                @if($members->isEmpty())
                    <p class="text-sm text-muted">No hay miembros todavía. Insértalos en el panel de Supabase.</p>
                @else
                    <ul class="space-y-2 mb-5">
                        @foreach($members as $member)
                            <li class="flex items-center gap-3">
                                <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white"
                                      style="background-color: {{ $member->color }}">
                                    {{ $member->initials() }}
                                </span>
                                <span class="text-sm">{{ $member->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                {{-- Identidad activa en este dispositivo --}}
                <div class="border-t divider pt-4">
                    <h3 class="text-sm font-semibold mb-2">Tu identidad en este dispositivo</h3>
                    @if(session('team_member_id') && $members->isNotEmpty())
                        @php $myMember = $members->firstWhere('id', session('team_member_id')) @endphp
                        @if($myMember)
                        <div class="flex items-center gap-3">
                            <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white"
                                  style="background-color: {{ $myMember->color }}">{{ $myMember->initials() }}</span>
                            <span class="text-sm font-medium">{{ $myMember->name }}</span>
                            <form method="POST" action="{{ route('team.identity.destroy') }}" id="desvincular-form" class="ml-auto">
                                @csrf @method('DELETE')
                                <button type="button" id="btn-desvincular"
                                        class="btn-ghost text-sm text-rose-600 dark:text-rose-400">
                                    Desvincularme
                                </button>
                            </form>
                        </div>
                        <p class="text-xs text-faint mt-2">Útil si cambias de dispositivo o alguien más va a usar este ordenador.</p>
                        @endif
                    @else
                        <p class="text-sm text-muted">Sin vincular en este dispositivo. Ve al <a href="{{ route('team.tasks.index') }}" class="underline">board del equipo</a> para seleccionar tu perfil.</p>
                    @endif
                </div>
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

<script>
// Si no hay sesión de equipo en este dispositivo, limpia también localStorage
@if(!session('team_member_id'))
localStorage.removeItem('team_member_id');
localStorage.removeItem('team_member_name');
@endif

// El botón Desvincularme limpia localStorage y luego envía el formulario
document.getElementById('btn-desvincular')?.addEventListener('click', () => {
    localStorage.removeItem('team_member_id');
    localStorage.removeItem('team_member_name');
    document.getElementById('desvincular-form').submit();
});
</script>
@endsection
