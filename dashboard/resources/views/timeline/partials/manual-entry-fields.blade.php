{{--
    Campos de una entrada manual. Reutilizado por el formulario de alta y el
    de edición. Espera: $projects (Collection), $tz (string) y, opcionalmente,
    $entry (ManualEntry|null) para prerrellenar.
--}}
@php $e = $entry ?? null; @endphp

<div class="grid grid-cols-2 gap-3">
    <label class="label">
        <span>Inicio</span>
        <input type="time" name="start_time" required class="input font-mono mt-1"
               value="{{ old('start_time', $e ? $e->starts_at->copy()->setTimezone($tz)->format('H:i') : '') }}">
    </label>
    <label class="label">
        <span>Fin</span>
        <input type="time" name="end_time" required class="input font-mono mt-1"
               value="{{ old('end_time', $e ? $e->ends_at->copy()->setTimezone($tz)->format('H:i') : '') }}">
    </label>
</div>

<label class="label">
    <span>Título</span>
    <input type="text" name="title" required maxlength="200" class="input mt-1"
           placeholder="ej. Reunión de planning semanal"
           value="{{ old('title', $e?->title) }}">
</label>

<div class="grid grid-cols-2 gap-3">
    <label class="label">
        <span>Tipo</span>
        <select name="kind" class="select mt-1">
            @foreach (\App\Enums\EntryKind::options() as $k)
                <option value="{{ $k->value }}"
                    @selected(old('kind', $e?->kind->value ?? 'meeting') === $k->value)>
                    {{ $k->label() }}
                </option>
            @endforeach
        </select>
    </label>
    <label class="label">
        <span>Proyecto</span>
        <select name="project_id" class="select mt-1">
            <option value="">— Sin proyecto —</option>
            @foreach ($projects as $p)
                <option value="{{ $p->id }}"
                    @selected((int) old('project_id', $e?->project_id) === $p->id)>
                    {{ $p->code }} · {{ $p->name }}
                </option>
            @endforeach
        </select>
    </label>
</div>

<label class="label">
    <span>Notas (opcional)</span>
    <textarea name="notes" rows="2" maxlength="1000"
              class="textarea mt-1">{{ old('notes', $e?->notes) }}</textarea>
</label>
