@php
    $overdue = $task->due_date
        && $task->status !== \App\Enums\TaskStatus::Done
        && $task->due_date->isPast() && ! $task->due_date->isToday();
    $logged = $task->loggedMinutes();
@endphp
<div class="task-card card p-2.5 cursor-grab active:cursor-grabbing"
     data-task-id="{{ $task->id }}"
     data-title="{{ $task->title }}"
     data-description="{{ $task->description }}"
     data-status="{{ $task->status->value }}"
     data-priority="{{ $task->priority?->value }}"
     data-project="{{ $task->project_id }}"
     data-due="{{ $task->due_date?->format('Y-m-d') }}">
    <div class="flex items-start justify-between gap-2">
        <p class="text-sm font-medium leading-snug">{{ $task->title }}</p>
        <button type="button" data-task-edit class="btn-ghost text-xs shrink-0 -mr-1 -mt-1"
                title="Editar tarea" aria-label="Editar tarea">✎</button>
    </div>
    @if ($task->project || $task->priority || $task->due_date || $logged > 0)
        <div class="flex flex-wrap items-center gap-1 mt-2">
            @if ($task->project)
                <span class="chip">
                    <span class="inline-block w-1.5 h-1.5 rounded-full mr-1"
                          style="background-color: {{ $task->project->color }}"></span>{{ $task->project->code }}
                </span>
            @endif
            @if ($task->priority)
                <span class="chip {{ $task->priority === \App\Enums\TaskPriority::High ? 'text-rose-600 dark:text-rose-400' : '' }}">{{ $task->priority->label() }}</span>
            @endif
            @if ($task->due_date)
                <span class="chip {{ $overdue ? 'text-rose-600 dark:text-rose-400 font-medium' : '' }}">{{ $task->due_date->format('d/m') }}</span>
            @endif
            @if ($logged > 0)
                <span class="chip" title="Tiempo registrado">⏱ {{ $logged >= 60 ? intdiv($logged, 60) . 'h ' . ($logged % 60) . 'm' : $logged . 'm' }}</span>
            @endif
        </div>
    @endif
</div>
