@extends('layouts.settings')

@section('title', __('export.title'))

@section('settings-content')
    <h1 class="text-xl font-semibold tracking-tight mb-6">{{ __('export.title') }}</h1>

    <form method="POST" action="{{ url('/export') }}" class="card p-6 max-w-2xl space-y-5">
        @csrf

        <div class="grid grid-cols-2 gap-4">
            <label class="label">
                <span>{{ __('export.from') }}</span>
                <input type="date" name="from" value="{{ $weekAgo }}" required class="input">
            </label>
            <label class="label">
                <span>{{ __('export.to') }}</span>
                <input type="date" name="to" value="{{ $today }}" required class="input">
            </label>
        </div>

        <fieldset>
            <legend class="text-xs uppercase tracking-wider text-muted mb-2">{{ __('export.projects') }}</legend>
            <div class="flex flex-wrap gap-3">
                @foreach ($projects as $project)
                    <label class="surface-soft inline-flex items-center gap-2 px-3 py-1.5 rounded cursor-pointer hover:opacity-90">
                        <input type="checkbox" name="projects[]" value="{{ $project->code }}" class="accent-emerald-500">
                        <span class="inline-block w-2 h-2 rounded-full" style="background: {{ $project->color }}"></span>
                        <span class="text-sm">{{ $project->code }}</span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="grid grid-cols-3 gap-4">
            <label class="label">
                <span>{{ __('export.min_confidence') }}</span>
                <select name="min_confidence" class="select">
                    <option value="low">{{ __('export.conf_low') }}</option>
                    <option value="medium">{{ __('export.conf_medium') }}</option>
                    <option value="high">{{ __('export.conf_high') }}</option>
                </select>
            </label>
            <label class="label">
                <span>{{ __('export.group_by') }}</span>
                <select name="group_by" class="select">
                    <option value="session">{{ __('export.group_session') }}</option>
                    <option value="project-day">{{ __('export.group_project_day') }}</option>
                </select>
            </label>
            <label class="label">
                <span>{{ __('export.format') }}</span>
                <select name="format" class="select">
                    <option value="txt">{{ __('export.format_txt') }}</option>
                    <option value="md">{{ __('export.format_md') }}</option>
                    <option value="csv">{{ __('export.format_csv') }}</option>
                </select>
            </label>
        </div>

        <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="include_idle" value="1" class="accent-emerald-500">
            {{ __('export.include_idle') }}
        </label>

        <div class="pt-2 border-t divider">
            <button type="submit" class="btn">{{ __('export.download') }}</button>
            <a href="{{ route('timeline.today') }}" class="btn-ghost ml-2">{{ __('export.cancel') }}</a>
        </div>
    </form>
@endsection
