@extends('layouts.settings')

@section('title', __('nav.settings_integrations'))

@section('settings-content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight">{{ __('settings.integrations_title') }}</h1>
        <p class="text-sm text-muted mt-1">
            {!! __('settings.integrations_desc') !!}
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
            <h2 class="text-base font-semibold mb-1">{{ __('projects.supabase_title') }}</h2>
            <p class="text-sm text-muted mb-4">
                {!! __('settings.supabase_desc') !!}
            </p>

            <div class="flex items-center gap-2 mb-6">
                @if($supConnected)
                    <span class="inline-flex items-center gap-1.5 text-sm text-emerald-600 dark:text-emerald-400 font-medium">
                        {{ __('projects.supabase_connected') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 text-sm text-amber-600 dark:text-amber-400 font-medium">
                        {{ __('projects.supabase_not_configured') }}
                    </span>
                    <span class="text-xs text-faint">
                        {!! __('projects.supabase_env_hint') !!}
                    </span>
                @endif
            </div>

            @if($supConnected)
                <h3 class="text-sm font-semibold mb-3">{{ __('projects.team_members_title') }}</h3>
                <p class="text-xs text-faint mb-3">{!! __('settings.team_members_desc') !!}</p>

                @if($members->isEmpty())
                    <p class="text-sm text-muted">{{ __('projects.team_no_members') }}</p>
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
                    <h3 class="text-sm font-semibold mb-2">{{ __('projects.your_identity_title') }}</h3>
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
                                    {{ __('projects.unlink_me') }}
                                </button>
                            </form>
                        </div>
                        <p class="text-xs text-faint mt-2">{{ __('settings.identity_unlink_hint') }}</p>
                        @endif
                    @else
                        <p class="text-sm text-muted">{{ __('settings.identity_no_link') }} Ve al <a href="{{ route('team.tasks.index') }}" class="underline">{{ __('settings.identity_team_link') }}</a> para seleccionar tu perfil.</p>
                    @endif
                </div>
            @endif
        </section>

        @if(\App\Services\ModuleVisibility::enabled('base44'))
        <hr class="border-ink-200 dark:border-ink-700">

        {{-- ── Base44 CRM ── --}}
        <section class="card p-5">
            <h2 class="text-base font-semibold mb-1">{{ __('projects.crm_title') }}</h2>
            <p class="text-sm text-muted mb-4">
                URL y token de la API REST del CRM. Cuando esté disponible, el comando
                <code class="chip">php artisan crm:sync</code> importará proyectos y tareas.
            </p>

            <form method="POST" action="{{ route('settings.integrations.save') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="base44-url">{{ __('projects.api_url_label') }}</label>
                    <input type="url" id="base44-url" name="base44_url" maxlength="255"
                           value="{{ old('base44_url', $base44Url) }}"
                           class="input" placeholder="{{ __('projects.api_url_ph') }}">
                    @error('base44_url')
                        <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="label" for="base44-token">{{ __('projects.bearer_token_label') }}</label>
                    <input type="password" id="base44-token" name="base44_token" maxlength="500"
                           value="{{ old('base44_token', $base44Token) }}"
                           class="input" placeholder="{{ __('projects.bearer_token_ph') }}">
                    @error('base44_token')
                        <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                    @enderror
                </div>
                <div class="pt-1">
                    <button type="submit" class="btn">{{ __('settings.save') }}</button>
                </div>
            </form>
        </section>
        @endif

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
