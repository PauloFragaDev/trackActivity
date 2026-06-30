@extends('layouts.app')

@section('title', __('dashboard.title'))

@section('content')
    @php
        $fmt = fn (int $m) => $m <= 0 ? '—' : ($m >= 60 ? intdiv($m, 60) . 'h ' . ($m % 60) . 'm' : $m . 'm');
        $now = \Carbon\CarbonImmutable::now($tz ?? 'UTC');
    @endphp

    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight">{{ __('dashboard.title') }}</h1>
        <p class="text-sm text-muted mt-1">{{ ucfirst($now->locale('es')->isoFormat('dddd, D [de] MMMM')) }}</p>
    </div>

    @if ($trackingEnabled)
        @if ($trackerStaleSince)
            <div class="card p-3 mb-5 text-sm flex items-start gap-2 border-amber-400/60 bg-amber-50 dark:bg-amber-500/10
                        text-amber-800 dark:text-amber-300">
                <x-icon name="alert-triangle" class="w-4 h-4 mt-0.5 shrink-0" />
                <span>
                    El tracker no registra actividad desde
                    <x-timestamp :at="$trackerStaleSince" class="text-amber-900 dark:text-amber-200 cursor-help" />.
                    {{ __('dashboard.check_daemon') }}
                </span>
            </div>
        @endif

        @if ($latestEvent)
            <div class="card p-3 mb-5 flex items-center gap-2 text-sm">
                <span class="text-muted shrink-0">{{ __('dashboard.now') }}</span>
                <span class="flex-1 min-w-0 truncate">
                    @if ($latestEvent->source === 'idle')
                        <span class="text-muted">{{ __('dashboard.idle') }}</span>
                    @else
                        @php
                            $cwdHint = data_get($latestEvent->metadata, 'cwd_hint');
                            $cmdHint = data_get($latestEvent->metadata, 'cmd_hint');
                        @endphp
                        {{ $latestEvent->app ?: 'Actividad' }}
                        @if ($latestEvent->title)<span class="text-muted"> · {{ $latestEvent->title }}</span>@endif
                        @if ($latestEvent->repo_name)
                            <span class="chip ml-1">{{ $latestEvent->repo_name }}@if ($latestEvent->branch):{{ $latestEvent->branch }}@endif</span>
                        @elseif ($cwdHint)
                            <span class="chip ml-1 inline-flex items-center gap-1">
                                <x-icon name="folder-open" class="w-3 h-3" /> {{ basename($cwdHint) }}
                            </span>
                        @endif
                        @if ($cmdHint)
                            <span class="text-muted text-xs ml-1">▶ {{ \Illuminate\Support\Str::limit($cmdHint, 60, '…') }}</span>
                        @endif
                    @endif
                </span>
                <x-timestamp :at="$latestEvent->occurred_at" class="shrink-0 text-xs" />
            </div>
        @endif

        {{-- Digest de insights del día (si el módulo está activo) --}}
        @if (! empty($insightsDigest))
            <a href="{{ route('insights.index') }}"
               class="card p-4 mb-5 block hover:bg-ink-50 dark:hover:bg-ink-800/40 transition">
                <div class="flex items-center justify-between gap-2 mb-1">
                    <h2 class="text-sm font-semibold flex items-center gap-1.5">
                        <x-icon name="sparkles" class="w-3.5 h-3.5 text-emerald-500" /> {{ __('dashboard.insights_digest') }}
                    </h2>
                    <span class="text-xs text-faint shrink-0">{{ __('dashboard.view_insights') }}</span>
                </div>
                <p class="text-sm leading-relaxed">{{ $insightsDigest['narrative'] }}</p>
                <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-xs text-muted">
                    <span>{{ __('dashboard.active_time') }} <span class="font-medium text-ink-700 dark:text-ink-200">{{ $fmt($insightsDigest['active_minutes']) }}</span></span>
                    <span>{{ __('dashboard.longest_focus') }} <span class="font-medium text-ink-700 dark:text-ink-200">{{ $fmt($insightsDigest['longest_focus_minutes']) }}</span></span>
                    <span>{{ __('dashboard.idle_time') }} <span class="font-medium text-ink-700 dark:text-ink-200">{{ $fmt($insightsDigest['idle_minutes']) }}</span></span>
                </div>
            </a>
        @endif

        {{-- Semana actual --}}
        <section class="mb-6">
            <h2 class="text-xs font-medium uppercase tracking-wider text-muted mb-2">{{ __('dashboard.this_week') }}</h2>
            <div class="grid grid-cols-7 gap-2">
                @foreach ($week as $d)
                    <a href="{{ route('timeline.day', ['date' => $d['date']->format('Y-m-d')]) }}"
                       class="card p-3 text-center transition hover:border-emerald-400/60
                              {{ $d['is_today'] ? 'ring-2 ring-[var(--ring)]' : '' }}">
                        <div class="text-[11px] uppercase tracking-wide text-muted">{{ $d['date']->locale('es')->isoFormat('ddd') }}</div>
                        <div class="text-lg font-medium leading-tight font-mono tabular-nums">{{ $d['date']->format('j') }}</div>
                        <div class="text-xs mt-0.5 {{ $d['minutes'] > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-faint' }}">
                            {{ $fmt($d['minutes']) }}
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        {{-- Heatmap de actividad del último año --}}
        <section class="mb-6">
            <h2 class="text-xs font-medium uppercase tracking-wider text-muted mb-2">{{ __('dashboard.heatmap') }}</h2>
            @php
                $heatLevel = fn ($m) => $m === null ? -1
                    : ($m == 0 ? 0 : ($m < 90 ? 1 : ($m < 210 ? 2 : ($m < 360 ? 3 : 4))));
                $heatClass = [
                    0 => 'bg-ink-100 dark:bg-ink-800',
                    1 => 'bg-emerald-200 dark:bg-emerald-900',
                    2 => 'bg-emerald-300 dark:bg-emerald-700',
                    3 => 'bg-emerald-400 dark:bg-emerald-600',
                    4 => 'bg-emerald-500 dark:bg-emerald-400',
                ];
            @endphp
            <div class="card p-3 overflow-x-auto">
                <div class="flex gap-[3px]">
                    @foreach ($heatmap as $week)
                        <div class="flex flex-col gap-[3px]">
                            @foreach ($week as $d)
                                @php $lv = $heatLevel($d['minutes']); @endphp
                                @if ($lv < 0)
                                    <div class="w-2.5 h-2.5"></div>
                                @else
                                    <a href="{{ route('timeline.day', ['date' => $d['date']->format('Y-m-d')]) }}"
                                       class="w-2.5 h-2.5 rounded-sm {{ $heatClass[$lv] }}"
                                       title="{{ $d['date']->locale('es')->isoFormat('D MMM YYYY') }} · {{ $fmt($d['minutes']) }}"></a>
                                @endif
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="flex items-center justify-end gap-1.5 mt-2 text-[11px] text-faint">
                <span>{{ __('dashboard.less') }}</span>
                <span class="w-2.5 h-2.5 rounded-sm {{ $heatClass[0] }}"></span>
                <span class="w-2.5 h-2.5 rounded-sm {{ $heatClass[1] }}"></span>
                <span class="w-2.5 h-2.5 rounded-sm {{ $heatClass[2] }}"></span>
                <span class="w-2.5 h-2.5 rounded-sm {{ $heatClass[3] }}"></span>
                <span class="w-2.5 h-2.5 rounded-sm {{ $heatClass[4] }}"></span>
                <span>{{ __('dashboard.more') }}</span>
            </div>
        </section>
    @endif

    {{-- Últimas notas + Tareas en curso --}}
    <div class="grid gap-4 md:grid-cols-2">
        <section class="card p-4">
            <h2 class="text-sm font-semibold mb-3">{{ __('dashboard.recent_notes') }}</h2>
            <div class="space-y-0.5">
                @forelse ($recentNotes as $n)
                    <a href="{{ route('notes.index', ['note' => $n->id]) }}"
                       class="flex items-center gap-1.5 px-2 py-1.5 rounded text-sm hover:bg-ink-100 dark:hover:bg-ink-800">
                        <span>{{ $n->icon ?: '📄' }}</span>
                        <span class="flex-1 truncate">{{ $n->title }}</span>
                        <x-timestamp :at="$n->updated_at" class="shrink-0 text-xs" />
                    </a>
                @empty
                    <p class="text-sm text-muted">{{ __('dashboard.no_recent_notes') }}</p>
                @endforelse
            </div>
        </section>

        <section class="card p-4">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold">{{ __('dashboard.tasks_in_progress') }}</h2>
                <a href="{{ route('tasks.index') }}" class="text-xs text-muted hover:underline">{{ __('dashboard.view_board') }}</a>
            </div>
            <div class="space-y-0.5">
                @forelse ($doingTasks as $t)
                    <a href="{{ route('tasks.index') }}"
                       class="flex items-center gap-2 px-2 py-1.5 rounded text-sm hover:bg-ink-100 dark:hover:bg-ink-800">
                        <span class="flex-1 truncate">{{ $t->title }}</span>
                        @if ($t->project)
                            <span class="chip shrink-0">{{ $t->project->code }}</span>
                        @endif
                    </a>
                @empty
                    <p class=”text-sm text-muted”>{{ __('dashboard.no_tasks_in_progress') }}</p>
                @endforelse
            </div>
        </section>
    </div>
@endsection
