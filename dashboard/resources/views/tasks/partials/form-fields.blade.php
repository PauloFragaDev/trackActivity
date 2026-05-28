{{-- Campos compartidos por los modales de alta y edición de tarea.
     Espera del scope: $columns, $priorities, $projects, $labels. --}}
<label class="label">
    <span>Título</span>
    <input type="text" name="title" required maxlength="200"
           class="input mt-1 @error('title') is-invalid @enderror"
           placeholder="¿Qué hay que hacer?">
    <x-field-error name="title" />
</label>
<div class="label">
    <span>Descripción</span>
    {{-- Editor Markdown con tabs Editar / Vista previa (patrón GitHub Issues).
         El textarea es la fuente de la verdad; el preview se renderiza
         on-demand al cambiar de tab. Render en JS con `marked`.
         Más detalle en resources/js/markdown-editor/README.md. --}}
    <div class="markdown-editor" data-markdown-editor>
        <div class="markdown-editor__tabs" role="tablist" aria-label="Modo de la descripción">
            <button type="button" data-tab="edit" role="tab"
                    aria-selected="true" tabindex="0"
                    class="markdown-editor__tab is-active">Editar</button>
            <button type="button" data-tab="preview" role="tab"
                    aria-selected="false" tabindex="-1"
                    class="markdown-editor__tab">Vista previa</button>
        </div>
        <div class="markdown-editor__panel" data-panel="edit">
            <textarea name="description" rows="6"
                      class="textarea font-mono text-[13px] leading-relaxed @error('description') is-invalid @enderror"
                      placeholder="Markdown opcional · `código`, **negrita**, [enlace](url), - lista..."></textarea>
        </div>
        <div class="markdown-editor__panel" data-panel="preview" hidden>
            <div data-markdown-preview class="markdown-editor__preview"></div>
        </div>
    </div>
    <x-field-error name="description" />
</div>
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
