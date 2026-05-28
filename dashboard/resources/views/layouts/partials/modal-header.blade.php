{{-- Cabecera estándar de un modal <dialog class="modal">.
     Espera: $title (string). Opcional: $hint (string, p.ej. "Esc para cerrar").
     La clase .modal-header aplica el divider inferior + padding extendido al
     ancho completo del modal (compensa el padding del .modal con margen negativo). --}}
<div class="modal-header">
    <h3 class="text-base font-semibold">{{ $title }}</h3>
    <div class="flex items-center gap-2">
        @if (! isset($hint) || $hint !== false)
            <span class="hidden md:inline-flex items-center gap-1 text-xs text-faint">
                @if (isset($hint))
                    {{ $hint }}
                @else
                    <x-kbd>Esc</x-kbd>
                    <span>para cerrar</span>
                @endif
            </span>
        @endif
        <button type="button" class="icon-btn" data-modal-close aria-label="Cerrar">
            <x-icon name="close" class="w-4 h-4" />
        </button>
    </div>
</div>
