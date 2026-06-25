@php
    $overdue = $task->due_date
        && $task->status !== \App\Enums\TaskStatus::Done
        && $task->due_date->isPast() && ! $task->due_date->isToday();
    $logged       = $task->loggedMinutes();
    $taskLabels   = $task->labels;
    $checkboxes   = $task->checkboxes;
    $checkboxDone = $checkboxes->where('checked', true)->count();
    $checkboxAll  = $checkboxes->count();
    $comments     = $task->comments;
    $hasChips     = $task->project || $task->priority || $task->due_date || $logged > 0 || $checkboxAll > 0 || $comments->isNotEmpty();
@endphp
<div class="task-card card p-2.5 cursor-grab active:cursor-grabbing relative
            {{ $task->status === \App\Enums\TaskStatus::Done ? 'opacity-70' : '' }}"
     @if ($task->project) style="--project-color: {{ $task->project->color }}" @endif
     data-task-id="{{ $task->id }}"
     data-title="{{ $task->title }}"
     data-description="{{ $task->description }}"
     data-status="{{ $task->status->value }}"
     data-priority="{{ $task->priority?->value }}"
     data-project="{{ $task->project_id }}"
     data-due="{{ $task->due_date?->format('Y-m-d') }}"
     data-labels="{{ $taskLabels->pluck('id')->toJson() }}"
     data-checkboxes="{{ $checkboxes->map(fn ($c) => ['id'=>$c->id,'title'=>$c->title,'checked'=>$c->checked])->toJson() }}"
     data-comments="{{ $comments->map(fn ($c) => ['id'=>$c->id,'body'=>$c->body,'created_at'=>$c->created_at?->toIso8601String(),'author_name'=>$c->author_name,'author_token'=>$c->author_token])->toJson() }}">
    <div class="flex items-start justify-between gap-2">
        <p class="text-sm font-medium leading-snug {{ $task->status === \App\Enums\TaskStatus::Done ? 'line-through text-muted' : '' }}">{{ $task->title }}</p>
        <div class="flex items-center gap-0.5 shrink-0 -mr-1 -mt-1">
            <button type="button" data-task-edit class="icon-btn"
                    title="Editar tarea" aria-label="Editar tarea">
                <x-icon name="edit" class="w-3.5 h-3.5" />
            </button>
        </div>
    </div>

    @if ($checkboxAll > 0)
        <div class="subtask-bar" title="{{ $checkboxDone }} de {{ $checkboxAll }} completadas">
            <span style="width: {{ (int) round(100 * $checkboxDone / $checkboxAll) }}%"></span>
        </div>
    @endif

    @if ($taskLabels->isNotEmpty())
        <div class="flex flex-wrap gap-1 mt-2">
            @foreach ($taskLabels as $label)
                <span class="text-[11px] rounded-full px-2 py-0.5 label-chip-tint"
                      style="--label-color: {{ $label->color }};">{{ $label->title }}</span>
            @endforeach
        </div>
    @endif

    @if ($hasChips)
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
                <span class="chip" title="Tiempo registrado"><x-icon name="clock" class="w-3 h-3" />{{ $logged >= 60 ? intdiv($logged, 60) . 'h ' . ($logged % 60) . 'm' : $logged . 'm' }}</span>
            @endif
            @if ($checkboxAll > 0)
                <span class="chip {{ $checkboxDone === $checkboxAll ? 'text-emerald-600 dark:text-emerald-400' : '' }}"
                      title="Subtareas" data-card-subtasks-badge><x-icon name="check" class="w-3 h-3" />{{ $checkboxDone }}/{{ $checkboxAll }}</span>
            @endif
            @if ($comments->isNotEmpty())
                <span class="chip" title="Comentarios" data-card-comments-badge><x-icon name="chat" class="w-3 h-3" />{{ $comments->count() }}</span>
            @endif
        </div>
    @endif

    @if(isset($task->assignee) && $task->assignee)
    <div class="flex justify-start mt-2">
        <span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-white select-none"
              style="background-color: {{ $task->assignee->color }}"
              title="{{ $task->assignee->name }}">{{ $task->assignee->initials() }}</span>
    </div>
    @endif
</div>
