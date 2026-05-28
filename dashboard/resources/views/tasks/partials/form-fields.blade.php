{{-- Campos compartidos por los modales de alta y edición de tarea.
     Espera del scope: $columns, $priorities, $projects, $labels. --}}
<label class="label">
    <span>Título</span>
    <input type="text" name="title" required maxlength="200"
           class="input mt-1 @error('title') is-invalid @enderror"
           placeholder="¿Qué hay que hacer?">
    <x-field-error name="title" />
</label>
<label class="label">
    <span>Descripción</span>
    {{-- Crepe (Markdown WYSIWYG) se monta sobre [data-task-desc-editor] al abrir
         el modal; el textarea queda oculto como campo del form y sincronizado. --}}
    <div data-task-desc-editor class="task-desc-editor mt-1" hidden></div>
    <textarea name="description" rows="3"
              class="textarea mt-1 @error('description') is-invalid @enderror"
              placeholder="Opcional · usa Markdown"></textarea>
    <x-field-error name="description" />
</label>
<div class="grid grid-cols-2 gap-3">
    <label class="label">
        <span>Columna</span>
        <select name="status" class="select mt-1 @error('status') is-invalid @enderror">
            @foreach ($columns as $c)
                <option value="{{ $c->value }}">{{ $c->label() }}</option>
            @endforeach
        </select>
        <x-field-error name="status" />
    </label>
    <label class="label">
        <span>Prioridad</span>
        <select name="priority" class="select mt-1 @error('priority') is-invalid @enderror">
            <option value="">— Sin prioridad —</option>
            @foreach ($priorities as $p)
                <option value="{{ $p->value }}">{{ $p->label() }}</option>
            @endforeach
        </select>
        <x-field-error name="priority" />
    </label>
    <label class="label">
        <span>Proyecto</span>
        <select name="project_id" class="select mt-1 @error('project_id') is-invalid @enderror">
            <option value="">— Sin proyecto —</option>
            @foreach ($projects as $pr)
                <option value="{{ $pr->id }}">{{ $pr->code }} · {{ $pr->name }}</option>
            @endforeach
        </select>
        <x-field-error name="project_id" />
    </label>
    <label class="label">
        <span>Vencimiento</span>
        <input type="date" name="due_date" class="input mt-1 @error('due_date') is-invalid @enderror">
        <x-field-error name="due_date" />
    </label>
</div>

@if (! ($labels ?? collect())->isEmpty())
    <div class="label">
        <span>Etiquetas</span>
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
