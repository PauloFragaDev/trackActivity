@props([])
{{-- Render para atajos de teclado. El styling vive en .kbd (app.css).
     Para mostrar ⌘ en macOS, escribe directamente el carácter ⌘ en el slot:
     el componente no detecta OS — esa decisión la toma JS aparte al cargar
     la página (kbd.js cambia "Ctrl" → "⌘" en navigator.platform Mac). --}}
<kbd {{ $attributes->merge(['class' => 'kbd']) }}>{{ $slot }}</kbd>
