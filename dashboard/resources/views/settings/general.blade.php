@extends('layouts.settings')

@section('title', __('settings.general_title'))

@section('settings-content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight">{{ __('settings.general_title') }}</h1>
        <p class="text-sm text-muted mt-1">
            {{ __('settings.general_desc') }}
        </p>
    </div>

    <form method="POST" action="{{ route('settings.general.save') }}"
          class="card p-5 max-w-2xl space-y-4" data-loading-form>
        @csrf

        <div class="space-y-2">
            <div>
                <h2 class="text-sm font-semibold">{{ __('settings.user_name') }}</h2>
                <p class="text-xs text-faint mt-0.5">
                    {{ __('settings.user_name_hint') }}
                </p>
            </div>
            <input type="text" name="user_name" maxlength="80"
                   value="{{ $userName }}" placeholder="{{ __('settings.user_name_ph') }}"
                   class="input text-sm max-w-xs @error('user_name') is-invalid @enderror">
            <x-field-error name="user_name" />
        </div>

        <div class="space-y-3 pt-4 border-t divider">
            <div>
                <h2 class="text-sm font-semibold">{{ __('settings.visible_modules') }}</h2>
                <p class="text-xs text-faint mt-0.5">
                    {{ __('settings.modules_desc') }}
                </p>
            </div>

            <div class="divide-y divide-ink-200 dark:divide-ink-700 -mx-2">
                @foreach ($modules as $slug => $m)
                    <label class="flex items-start gap-3 px-2 py-3 cursor-pointer hover:bg-ink-50 dark:hover:bg-ink-800/40 rounded transition">
                        <input type="checkbox"
                               name="modules[{{ $slug }}]"
                               value="1"
                               @checked($m['enabled'])
                               class="mt-1 h-4 w-4 rounded border-ink-300 dark:border-ink-600
                                      text-emerald-600 focus:ring-emerald-500">
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-medium">{{ $m['label'] }}</span>
                            <span class="block text-xs text-faint mt-0.5">{{ $m['description'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="space-y-2 pt-4 border-t divider">
            <div>
                <h2 class="text-sm font-semibold">{{ __('settings.language') }}</h2>
                <p class="text-xs text-faint mt-0.5">
                    {{ __('settings.language_desc') }}
                </p>
            </div>
            <select name="locale" class="select mt-1">
                <option value="es" @selected(\App\Models\Setting::get('app.locale', 'es') === 'es')>{{ __('common.spanish') }}</option>
                <option value="ca" @selected(\App\Models\Setting::get('app.locale', 'es') === 'ca')>{{ __('common.catalan') }}</option>
            </select>
        </div>

        <div class="pt-3 flex items-center justify-between border-t divider">
            <p class="text-xs text-faint">
                {{ __('settings.save_hint') }}
            </p>
            <button type="submit" class="btn">{{ __('settings.save') }}</button>
        </div>
    </form>
@endsection
