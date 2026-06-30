{{-- Campos compartidos por los modales de alta y edición de tarea.
     Espera del scope: $columns, $priorities, $projects, $labels. --}}
<label class="label">
    <span>{{ __('tasks.field_title') }}</span>
    <input type="text" name="title" required maxlength="200"
           class="input mt-1 @error('title') is-invalid @enderror"
           placeholder="{{ __('tasks.field_what') }}">
    <x-field-error name="title" />
</label>
<label class="label">
    <span>{{ __('tasks.field_description') }}</span>
    <textarea name="description" rows="5"
              class="textarea mt-1 @error('description') is-invalid @enderror"
              placeholder="{{ __('tasks.field_description_ph') }}"></textarea>
    <x-field-error name="description" />
</label>
<div class="grid grid-cols-2 gap-3">
    <label class="label">
        <span>{{ __('tasks.field_column') }}</span>
        <select name="status" class="select mt-1 @error('status') is-invalid @enderror">
            @foreach ($columns as $c)
                <option value="{{ $c->value }}">{{ $c->label() }}</option>
            @endforeach
        </select>
        <x-field-error name="status" />
    </label>
    <label class="label">
        <span>{{ __('tasks.field_priority') }}</span>
        <select name="priority" class="select mt-1 @error('priority') is-invalid @enderror">
            <option value="">{{ __('tasks.field_no_priority') }}</option>
            @foreach ($priorities as $p)
                <option value="{{ $p->value }}">{{ $p->label() }}</option>
            @endforeach
        </select>
        <x-field-error name="priority" />
    </label>
    <label class="label">
        <span>{{ __('tasks.field_project') }}</span>
        <select name="project_id" class="select mt-1 @error('project_id') is-invalid @enderror">
            <option value="">{{ __('tasks.field_no_project') }}</option>
            @foreach ($projects as $pr)
                <option value="{{ $pr->id }}">{{ $pr->code }} · {{ $pr->name }}</option>
            @endforeach
        </select>
        <x-field-error name="project_id" />
    </label>
    <label class="label">
        <span>{{ __('tasks.field_due_date') }}</span>
        <input type="date" name="due_date" class="input mt-1 @error('due_date') is-invalid @enderror">
        <x-field-error name="due_date" />
    </label>
</div>

@if(isset($mode) && $mode === 'team' && isset($members) && $members->isNotEmpty())
<div>
    <label class="label" for="field-assignee">{{ __('tasks.field_assignee') }}</label>
    <select name="assignee_id" id="field-assignee" class="select">
        <option value="">{{ __('tasks.field_no_assignee') }}</option>
        @foreach($members as $member)
            <option value="{{ $member->id }}"
                @selected((isset($task) && $task->assignee_id === $member->id)
                           || (old('assignee_id') == $member->id))>
                {{ $member->name }}
            </option>
        @endforeach
    </select>
</div>
@endif

@if (! ($labels ?? collect())->isEmpty())
    <div class="label">
        <span>{{ __('tasks.field_labels') }}</span>
        <div class="flex flex-wrap gap-1.5 mt-1" data-task-labels>
            @foreach ($labels as $label)
                <label class="task-label-chip cursor-pointer inline-flex items-center gap-1 text-xs border rounded-full px-2 py-0.5 transition"
                       style="border-color: {{ $label->color }}; color: {{ $label->color }};">
                    <input type="checkbox" name="label_ids[]" value="{{ $label->id }}" class="sr-only" data-label-id="{{ $label->id }}">
                    {{ $label->title }}
                </label>
            @endforeach
        </div>
    </div>
@endif
