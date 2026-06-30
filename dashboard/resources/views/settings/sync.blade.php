@extends('layouts.settings')

@section('title', __('nav.settings_sync'))

@section('settings-content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight">{{ __('settings.sync_title') }}</h1>
        <p class="text-sm text-muted mt-1">
            {{ __('settings.sync_desc') }}
        </p>
    </div>

    <form method="POST" action="{{ route('settings.sync.save') }}"
          class="card p-5 max-w-2xl space-y-4" data-loading-form>
        @csrf

        <div class="divide-y divide-ink-200 dark:divide-ink-700 -mx-2">
            <label class="flex items-start gap-3 px-2 py-3 cursor-pointer hover:bg-ink-50 dark:hover:bg-ink-800/40 rounded transition">
                <input type="checkbox" name="extension" value="1" @checked($extension)
                       class="mt-1 h-4 w-4 rounded border-ink-300 dark:border-ink-600 text-emerald-600 focus:ring-emerald-500">
                <span class="flex-1 min-w-0">
                    <span class="block text-sm font-medium">{{ __('projects.sync_kanban_label') }}</span>
                    <span class="block text-xs text-faint mt-0.5">
                        {!! __('settings.sync_kanban_desc') !!}
                    </span>
                </span>
            </label>

            @if(\App\Services\ModuleVisibility::enabled('base44'))
            <label class="flex items-start gap-3 px-2 py-3 cursor-pointer hover:bg-ink-50 dark:hover:bg-ink-800/40 rounded transition">
                <input type="checkbox" name="crm" value="1" @checked($crm)
                       class="mt-1 h-4 w-4 rounded border-ink-300 dark:border-ink-600 text-emerald-600 focus:ring-emerald-500">
                <span class="flex-1 min-w-0">
                    <span class="block text-sm font-medium">{{ __('projects.sync_crm_label') }}</span>
                    <span class="block text-xs text-faint mt-0.5">
                        {!! __('settings.sync_crm_desc') !!}
                    </span>
                </span>
            </label>
            @endif
        </div>

        <div class="pt-3 flex items-center justify-end border-t divider">
            <button type="submit" class="btn">{{ __('settings.save') }}</button>
        </div>
    </form>
@endsection
