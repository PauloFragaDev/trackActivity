@props(['icon' => 'sparkles', 'title' => null, 'text' => null])
{{-- Empty state estándar: icono circular + título + texto + slot opcional
     para CTAs. Uniforma el patrón que hoy se repite a mano en cada vista
     (board vacío, archived vacío, reports sin datos, etc.). --}}
<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <div class="empty-state__icon">
        <x-icon :name="$icon" class="w-6 h-6" />
    </div>
    @if ($title)
        <h3 class="empty-state__title">{{ $title }}</h3>
    @endif
    @if ($text)
        <p class="empty-state__text">{{ $text }}</p>
    @endif
    @if (trim($slot) !== '')
        <div class="empty-state__actions">{{ $slot }}</div>
    @endif
</div>
