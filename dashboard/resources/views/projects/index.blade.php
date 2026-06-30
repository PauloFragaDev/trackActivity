@extends('layouts.settings')

@section('title', __('projects.title'))

@section('settings-content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">{{ __('projects.title') }}</h1>
            <p class="text-sm text-muted mt-1">{{ $projects->count() }} proyectos definidos.</p>
        </div>
        <a href="{{ route('projects.create') }}" class="btn">{{ __('projects.new') }}</a>
    </div>

    @if ($projects->isEmpty())
        <div class="card p-10 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-ink-100 dark:bg-ink-800 text-ink-500 mb-3">
                <x-icon name="folder" class="w-6 h-6" />
            </div>
            <h3 class="text-base font-semibold mb-1">{{ __('projects.empty_title') }}</h3>
            <p class="text-sm text-muted mb-4">{{ __('projects.empty_desc') }}</p>
            <a href="{{ route('projects.create') }}" class="btn inline-flex items-center gap-1">
                <x-icon name="plus" class="w-4 h-4" /> {{ __('projects.create_first') }}
            </a>
        </div>
    @else
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="surface-soft text-xs uppercase tracking-wider text-muted">
                    <tr>
                        <th class="text-left px-4 py-3">{{ __('projects.col_code') }}</th>
                        <th class="text-left px-4 py-3">{{ __('projects.col_name') }}</th>
                        <th class="text-left px-4 py-3">{{ __('projects.col_color') }}</th>
                        <th class="text-right px-4 py-3">{{ __('projects.col_mappings') }}</th>
                        <th class="text-right px-4 py-3">{{ __('projects.col_blocks') }}</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($projects as $p)
                        <tr class="border-t divider">
                            <td class="px-4 py-3 font-mono">{{ $p->code }}</td>
                            <td class="px-4 py-3">{{ $p->name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center gap-2">
                                    <span class="inline-block w-3 h-3 rounded" style="background: {{ $p->color ?? '#777' }}"></span>
                                    <span class="font-mono text-xs text-muted">{{ $p->color ?? '—' }}</span>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{{ $p->mappings_count }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $p->time_blocks_count }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('projects.edit', $p) }}" class="btn-ghost">{{ __('projects.edit_btn') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
