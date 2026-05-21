{{-- Campos compartidos por los modales de alta y edición de tarea.
     Espera del scope: $columns, $priorities, $projects. --}}
<label class="label">
    <span>Título</span>
    <input type="text" name="title" required maxlength="200" class="input mt-1" placeholder="¿Qué hay que hacer?">
</label>
<label class="label">
    <span>Descripción</span>
    <textarea name="description" rows="3" class="textarea mt-1" placeholder="Opcional"></textarea>
</label>
<div class="grid grid-cols-2 gap-3">
    <label class="label">
        <span>Columna</span>
        <select name="status" class="select mt-1">
            @foreach ($columns as $c)
                <option value="{{ $c->value }}">{{ $c->label() }}</option>
            @endforeach
        </select>
    </label>
    <label class="label">
        <span>Prioridad</span>
        <select name="priority" class="select mt-1">
            <option value="">— Sin prioridad —</option>
            @foreach ($priorities as $p)
                <option value="{{ $p->value }}">{{ $p->label() }}</option>
            @endforeach
        </select>
    </label>
    <label class="label">
        <span>Proyecto</span>
        <select name="project_id" class="select mt-1">
            <option value="">— Sin proyecto —</option>
            @foreach ($projects as $pr)
                <option value="{{ $pr->id }}">{{ $pr->code }} · {{ $pr->name }}</option>
            @endforeach
        </select>
    </label>
    <label class="label">
        <span>Vencimiento</span>
        <input type="date" name="due_date" class="input mt-1">
    </label>
</div>
