@extends('layouts.app')

@section('title', 'Timeline · ' . $day->isoFormat('dddd D MMM YYYY'))

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-semibold tracking-tight">
                {{ ucfirst($day->locale('es')->isoFormat('dddd D MMM YYYY')) }}
            </h1>
            <p class="text-sm text-muted mt-1">
                {{ count($sessions) }} {{ Str::plural('sesión', count($sessions)) }}
                @if ($manualEntries->isNotEmpty())
                    · {{ $manualEntries->count() }} {{ Str::plural('entrada manual', $manualEntries->count()) }}
                @endif
                · {{ $eventCount }} señales crudas
                @if ($totalMinutes > 0)
                    · {{ intdiv($totalMinutes, 60) }}h {{ $totalMinutes % 60 }}m totales
                @endif
            </p>
        </div>

        <div class="flex items-center gap-1">
            <a class="btn-ghost" href="{{ route('timeline.day', ['date' => $prevDay]) }}" title="Día anterior">←</a>
            <a class="btn-ghost" href="{{ route('timeline.today') }}">Hoy</a>
            <a class="btn-ghost" href="{{ route('timeline.day', ['date' => $nextDay]) }}" title="Día siguiente">→</a>
        </div>
    </div>

    {{-- Mini-gantt: barra horizontal del día (00→24h) con cada sesión como bloque. --}}
    @if (! empty($sessions))
        <div class="card p-3 mb-6">
            <div class="text-[10px] text-muted flex justify-between mb-1.5 px-0.5">
                <span>0h</span><span>6h</span><span>12h</span><span>18h</span><span>24h</span>
            </div>
            <div class="relative h-6 bg-ink-100 dark:bg-ink-800 rounded overflow-hidden">
                @foreach ($sessions as $s)
                    @php
                        $st  = $s['starts_at_local'];
                        $en  = $s['ends_at_local'];
                        $beg = $st->hour * 60 + $st->minute;
                        $end = $en->hour * 60 + $en->minute;
                        if ($end <= $beg) { $end = $beg + 1; }  // sesión que pasa de medianoche → recortar visualmente
                        $left  = ($beg / 1440) * 100;
                        $width = max(0.25, (($end - $beg) / 1440) * 100);
                        $color = $s['project']?->color ?? '#94a3b8';
                        $label = sprintf('%s–%s · %s', $st->format('H:i'), $en->format('H:i'), $s['project']?->code ?? 'Sin proyecto');
                    @endphp
                    <div class="absolute top-0 h-full rounded-sm transition hover:brightness-110"
                         style="left: {{ $left }}%; width: {{ $width }}%;
                                background-color: {{ $color }}; opacity: {{ $s['is_idle'] ? 0.25 : 0.75 }};"
                         title="{{ $label }}"></div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div id="form-errors" class="card p-4 mb-4 border-rose-400/60 text-rose-700 dark:text-rose-300">
            <ul class="list-disc pl-5 space-y-0.5 text-sm">
                @foreach ($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($totals->isNotEmpty())
        <div class="card p-4 mb-6">
            <h2 class="text-xs uppercase tracking-wider text-muted mb-3">Totales por proyecto</h2>
            <div class="flex flex-wrap gap-2">
                @foreach ($totals as $row)
                    <div class="surface-soft flex items-center gap-2 px-3 py-1.5 rounded">
                        @if ($row['project'])
                            <span class="inline-block w-2 h-2 rounded-full"
                                  style="background: {{ $row['project']->color ?? '#777' }}"></span>
                            <span class="text-sm font-medium">{{ $row['project']->code }}</span>
                        @else
                            <span class="inline-block w-2 h-2 rounded-full bg-ink-400 dark:bg-ink-500"></span>
                            <span class="text-sm font-medium text-muted">Sin proyecto</span>
                        @endif
                        <span class="text-xs font-mono text-muted">
                            {{ intdiv($row['minutes'], 60) }}h {{ $row['minutes'] % 60 }}m
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if (empty($timeline))
        <div class="card p-8 text-center text-muted">
            <p class="text-base">Sin actividad reconstruida este día.</p>
            <p class="mt-2 text-xs">
                Si el daemon está corriendo y deberías tener actividad,
                ejecuta <code class="chip">php artisan tracker:rebuild-blocks --day={{ $day->toDateString() }}</code>
                — o añade una entrada manual abajo.
            </p>
        </div>
    @else
        <ol class="space-y-3">
            @foreach ($timeline as $item)
                @if ($item['type'] === 'session')
                    @php
                        $session = $item['session'];
                        $confColor = match ($session['confidence_label']) {
                            'Alta'    => 'text-emerald-600 dark:text-emerald-400 border-emerald-400/40',
                            'Media'   => 'text-amber-600 dark:text-amber-300 border-amber-400/40',
                            'Baja'    => 'text-rose-600 dark:text-rose-300 border-rose-400/40',
                            'editado' => 'text-sky-600 dark:text-sky-300 border-sky-400/40',
                            default   => 'text-muted divider',
                        };
                    @endphp
                    <li class="card p-4">
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <span class="font-mono text-sm text-muted">
                                {{ $session['starts_at_local']->format('H:i') }}
                                <span class="text-faint">→</span>
                                {{ $session['ends_at_local']->format('H:i') }}
                            </span>
                            <span class="chip">{{ $session['duration_minutes'] }}m</span>
                            @if ($session['project'])
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium"
                                      style="background: {{ $session['project']->color ?? '#374151' }}22;
                                             color: {{ $session['project']->color ?? '#9ca3af' }};">
                                    <span class="inline-block w-1.5 h-1.5 rounded-full"
                                          style="background: {{ $session['project']->color ?? '#9ca3af' }}"></span>
                                    {{ $session['project']->code }}
                                </span>
                            @elseif ($session['is_idle'])
                                <span class="chip">idle</span>
                            @else
                                <span class="chip">sin proyecto</span>
                            @endif
                            @if ($session['confidence_label'] !== 'idle' && $session['confidence_label'] !== 'n/a')
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] uppercase tracking-wider border {{ $confColor }}">
                                    {{ $session['confidence_label'] }}
                                    @if ($session['confidence_label'] !== 'editado' && $session['confidence'] !== null)
                                        <span class="font-mono opacity-70">{{ number_format($session['confidence'], 2) }}</span>
                                    @endif
                                </span>
                            @endif
                            <span class="chip">{{ $session['block_count'] }} bloque{{ $session['block_count'] === 1 ? '' : 's' }}</span>
                        </div>

                        @if (! empty($session['summary']))
                            <p class="mt-2 text-sm leading-relaxed">{{ $session['summary'] }}</p>
                        @endif

                        <div class="mt-2 flex items-center gap-4">
                            <details class="group">
                                <summary class="cursor-pointer text-xs text-muted hover:opacity-100 opacity-80 select-none">
                                    {{ $session['evidence']->count() }} señal{{ $session['evidence']->count() === 1 ? '' : 'es' }}
                                    {{ $session['project'] === null ? 'sin atribuir' : 'en evidencia' }} ·
                                    <span class="underline-offset-2 group-hover:underline">expandir</span>
                                </summary>
                                <ul class="mt-2 space-y-1 text-xs font-mono text-muted border-l divider pl-3">
                                    @foreach ($session['evidence']->take(30) as $event)
                                        @php
                                            $cwdHint = data_get($event->metadata, 'cwd_hint');
                                            $cmdHint = data_get($event->metadata, 'cmd_hint');
                                        @endphp
                                        <li class="flex items-center gap-1 group">
                                            <span class="truncate flex-1 min-w-0">
                                                <span class="text-faint">{{ \Carbon\Carbon::parse($event->occurred_at)->setTimezone($tz)->format('H:i:s') }}</span>
                                                <span class="text-faint">[{{ $event->source }}]</span>
                                                {{ $event->title ?? $event->repo_name ?? $event->url ?? $event->subject ?? '—' }}
                                                @if ($event->branch)
                                                    <span class="text-faint">· {{ $event->branch }}</span>
                                                @endif
                                                @if ($event->modified_files)
                                                    <span class="text-faint">· +{{ $event->modified_files }}</span>
                                                @endif
                                                @if ($cwdHint)
                                                    <span class="text-faint">· 📂 {{ $cwdHint }}</span>
                                                @endif
                                                @if ($cmdHint)
                                                    <span class="text-faint">· ▶ {{ \Illuminate\Support\Str::limit($cmdHint, 80, '…') }}</span>
                                                @endif
                                                @if ($event->project)
                                                    <span class="chip ml-1" title="Atribuido manualmente">▶ {{ $event->project->code }}</span>
                                                @endif
                                            </span>
                                            <button type="button" class="icon-btn opacity-0 group-hover:opacity-100 focus:opacity-100"
                                                    data-event-edit
                                                    data-id="{{ $event->id }}"
                                                    data-time="{{ \Carbon\Carbon::parse($event->occurred_at)->setTimezone($tz)->format('H:i:s') }}"
                                                    data-source="{{ $event->source }}"
                                                    data-app="{{ $event->app }}"
                                                    data-title="{{ $event->title ?? $event->repo_name ?? $event->url ?? $event->subject ?? '' }}"
                                                    data-cwd="{{ $cwdHint }}"
                                                    data-cmd="{{ $cmdHint }}"
                                                    data-project-id="{{ $event->project_id }}"
                                                    title="Editar evento" aria-label="Editar evento">✎</button>
                                        </li>
                                    @endforeach
                                    @if ($session['evidence']->count() > 30)
                                        <li class="text-faint">… y {{ $session['evidence']->count() - 30 }} más</li>
                                    @endif
                                </ul>
                            </details>

                            @unless ($session['is_idle'])
                                <details class="group">
                                    <summary class="cursor-pointer text-xs text-muted hover:opacity-100 opacity-80 select-none">
                                        <span class="underline-offset-2 group-hover:underline">editar sesión</span>
                                    </summary>

                                    <form method="POST" action="{{ route('blocks.update') }}"
                                          class="surface-soft mt-2 p-3 rounded space-y-3">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                                        @foreach ($session['block_ids'] as $bid)
                                            <input type="hidden" name="block_ids[]" value="{{ $bid }}">
                                        @endforeach

                                        <label class="label">
                                            <span>Proyecto</span>
                                            <select name="project_id" class="select mt-1">
                                                <option value="">— Sin proyecto —</option>
                                                @foreach ($projects as $p)
                                                    <option value="{{ $p->id }}"
                                                        @selected($session['project'] && $session['project']->id === $p->id)>
                                                        {{ $p->code }} · {{ $p->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>

                                        <label class="label">
                                            <span>Resumen (opcional, sobrescribe el generado)</span>
                                            <textarea name="summary_text" rows="2" maxlength="500"
                                                      class="textarea mt-1"
                                                      placeholder="Deja vacío para conservar el resumen actual">{{ $session['status'] === 'edited' ? $session['summary'] : '' }}</textarea>
                                        </label>

                                        <div class="flex items-center gap-2">
                                            <button type="submit" class="btn">Guardar</button>
                                            @if ($session['status'] === 'edited')
                                                <button type="submit"
                                                        class="btn-ghost"
                                                        formaction="{{ route('blocks.reset') }}"
                                                        title="Devuelve la sesión a modo automático">
                                                    Volver a automático
                                                </button>
                                            @endif
                                        </div>
                                        <p class="text-[11px] text-faint">
                                            Al guardar, los {{ $session['block_count'] }} bloque(s) de esta sesión quedan
                                            marcados como <code class="chip">editado</code> y no se recalcularán en los rebuilds.
                                        </p>
                                    </form>
                                </details>
                            @endunless
                        </div>
                    </li>
                @else
                    @php $entry = $item['entry']; @endphp
                    <li class="card p-4" style="border-left: 3px solid {{ $entry->kind->color() }}">
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <span class="font-mono text-sm text-muted">
                                {{ $entry->starts_at->copy()->setTimezone($tz)->format('H:i') }}
                                <span class="text-faint">→</span>
                                {{ $entry->ends_at->copy()->setTimezone($tz)->format('H:i') }}
                            </span>
                            <span class="chip">{{ $entry->durationMinutes() }}m</span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium"
                                  style="background: {{ $entry->kind->color() }}22; color: {{ $entry->kind->color() }}">
                                {{ $entry->kind->label() }}
                            </span>
                            @if ($entry->project)
                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-medium"
                                      style="background: {{ $entry->project->color ?? '#374151' }}22;
                                             color: {{ $entry->project->color ?? '#9ca3af' }};">
                                    <span class="inline-block w-1.5 h-1.5 rounded-full"
                                          style="background: {{ $entry->project->color ?? '#9ca3af' }}"></span>
                                    {{ $entry->project->code }}
                                </span>
                            @endif
                            <span class="chip">manual</span>
                        </div>

                        <p class="mt-2 text-sm font-medium leading-relaxed">{{ $entry->title }}</p>
                        @if ($entry->notes)
                            <p class="mt-1 text-sm text-muted leading-relaxed">{{ $entry->notes }}</p>
                        @endif

                        <div class="mt-2 flex items-center gap-3 text-xs">
                            <button type="button"
                                    class="text-muted hover:text-ink-900 dark:hover:text-ink-50 underline underline-offset-2 decoration-dotted"
                                    data-modal-open="#manual-edit-{{ $entry->id }}">
                                editar entrada
                            </button>
                            <form method="POST" action="{{ route('manual-entries.destroy', $entry) }}"
                                  class="inline" data-confirm="¿Eliminar esta entrada manual?">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                                <input type="hidden" name="return" value="day">
                                <button type="submit"
                                        class="text-rose-600 dark:text-rose-400 underline underline-offset-2 decoration-dotted">
                                    eliminar
                                </button>
                            </form>
                        </div>

                        <dialog id="manual-edit-{{ $entry->id }}" class="modal">
                            <form method="POST" action="{{ route('manual-entries.update', $entry) }}" class="space-y-3">
                                @csrf
                                @method('PATCH')
                                @include('layouts.partials.modal-header', ['title' => 'Editar entrada manual'])
                                <input type="hidden" name="date" value="{{ $day->toDateString() }}">
                                <input type="hidden" name="return" value="day">
                                @include('timeline.partials.manual-entry-fields', ['entry' => $entry])
                                <div class="modal-footer flex items-center justify-end gap-2">
                                    <button type="button" class="btn-ghost" data-modal-close>Cancelar</button>
                                    <button type="submit" class="btn">Guardar cambios</button>
                                </div>
                            </form>
                        </dialog>
                    </li>
                @endif
            @endforeach
        </ol>
    @endif

    {{-- ─────── Añadir entrada manual ─────── --}}
    <div class="mt-6">
        <button type="button" class="btn" data-modal-open="#manual-add">
            + Añadir entrada manual
        </button>
    </div>

    <dialog id="manual-add" class="modal">
        <form method="POST" action="{{ route('manual-entries.store') }}" class="space-y-3">
            @csrf
            @include('layouts.partials.modal-header', ['title' => 'Nueva entrada manual'])
            <p class="-mt-1 text-xs text-muted">Reunión, corrección de horas… para el {{ $day->format('d/m/Y') }}.</p>
            <input type="hidden" name="date" value="{{ $day->toDateString() }}">
            <input type="hidden" name="return" value="day">
            @include('timeline.partials.manual-entry-fields', ['entry' => null])
            <div class="modal-footer flex items-center justify-end gap-2">
                <button type="button" class="btn-ghost" data-modal-close>Cancelar</button>
                <button type="submit" class="btn">Añadir</button>
            </div>
        </form>
    </dialog>

    {{-- ─── Modal: editar un activity_event (asignar proyecto a mano) ─── --}}
    <dialog id="event-edit" class="modal" data-event-edit-modal>
        @include('layouts.partials.modal-header', ['title' => 'Editar evento'])

        <dl class="text-sm space-y-1 mb-4">
            <div class="flex gap-2"><dt class="w-20 shrink-0 text-muted">Hora</dt><dd data-event-time></dd></div>
            <div class="flex gap-2"><dt class="w-20 shrink-0 text-muted">Fuente</dt><dd data-event-source></dd></div>
            <div class="flex gap-2"><dt class="w-20 shrink-0 text-muted">App</dt><dd data-event-app class="flex-1 min-w-0 truncate"></dd></div>
            <div class="flex gap-2"><dt class="w-20 shrink-0 text-muted">Título</dt><dd data-event-title class="flex-1 min-w-0 truncate"></dd></div>
            <div class="flex gap-2 hidden" data-event-cwd-row><dt class="w-20 shrink-0 text-muted">Dir</dt><dd data-event-cwd class="flex-1 min-w-0 truncate font-mono text-xs"></dd></div>
            <div class="flex gap-2 hidden" data-event-cmd-row><dt class="w-20 shrink-0 text-muted">Cmd</dt><dd data-event-cmd class="flex-1 min-w-0 truncate font-mono text-xs"></dd></div>
        </dl>

        <form method="POST" data-event-edit-form class="space-y-3">
            @csrf
            @method('PATCH')
            <label class="label">
                <span>Proyecto</span>
                <select name="project_id" class="select mt-1">
                    <option value="">— Sin proyecto (atribución automática) —</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} · {{ $p->name }}</option>
                    @endforeach
                </select>
            </label>
            <p class="text-xs text-muted">
                Al guardar, el bloque que contiene este evento se reatribuirá al proyecto elegido.
            </p>
            <div class="modal-footer flex items-center justify-end gap-2">
                <button type="button" class="btn-ghost" data-modal-close>Cancelar</button>
                <button type="submit" class="btn">Guardar</button>
            </div>
        </form>
    </dialog>
@endsection
