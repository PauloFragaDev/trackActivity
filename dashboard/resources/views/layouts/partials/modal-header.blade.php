{{-- Cabecera estándar de un modal <dialog class="modal">.
     Espera: $title (string). Opcional: $hint (string, p.ej. "Esc para cerrar").
     La clase .modal-header aplica el divider inferior + padding extendido al
     ancho completo del modal (compensa el padding del .modal con margen negativo). --}}
<div class="modal-header">
    <h3 class="text-base font-semibold">{{ $title }}</h3>
    <div class="flex items-center gap-2">
        <span class="text-[10px] text-faint hidden md:inline">{{ $hint ?? 'Esc para cerrar' }}</span>
        <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">
            <x-icon name="close" class="w-4 h-4" />
        </button>
    </div>
</div>
