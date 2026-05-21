{{-- Selector de icono (emoji). Espera $value (string|null).
     app.js cablea los botones [data-icon-set] sobre el input[name=icon]. --}}
<div data-icon-field class="flex items-center gap-2 flex-wrap">
    <input type="text" name="icon" maxlength="16" value="{{ $value }}"
           class="input w-14 text-center text-lg shrink-0" placeholder="—" aria-label="Icono">
    <div class="flex flex-wrap gap-0.5">
        @foreach (['📄','📝','📁','💡','✅','⭐','🔖','📌','📅','🎯','💻','📊','🧠','🚀','🐛','📖'] as $emoji)
            <button type="button" data-icon-set data-icon-value="{{ $emoji }}"
                    class="text-lg leading-none px-1.5 py-1 rounded hover:bg-ink-100 dark:hover:bg-ink-800">{{ $emoji }}</button>
        @endforeach
        <button type="button" data-icon-set data-icon-value=""
                class="text-xs px-2 py-1 rounded text-muted hover:bg-ink-100 dark:hover:bg-ink-800">sin icono</button>
    </div>
</div>
