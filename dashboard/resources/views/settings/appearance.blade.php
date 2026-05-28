@extends('layouts.settings')

@section('title', 'Apariencia')

@section('settings-content')
    <div class="mb-5">
        <h1 class="text-xl font-semibold tracking-tight">Apariencia</h1>
        <p class="text-sm text-muted mt-1">
            Elige una paleta. El modo claro/oscuro lo sigues controlando con el botón
            del sidebar — el tema funciona en los dos.
        </p>
    </div>

    <div class="theme-grid"
         data-theme-grid
         data-current="{{ $current }}"
         data-save-url="{{ route('settings.appearance.save') }}">
        @foreach ($themes as $id => $meta)
            <button type="button"
                    class="theme-card {{ $current === $id ? 'theme-card--active' : '' }}"
                    data-theme-id="{{ $id }}">
                {{-- Mini-preview de la paleta: tres bloques (paper, ink, accent). --}}
                <div class="theme-card__preview"
                     style="background: {{ $meta['swatch']['paper'] }}">
                    <div class="theme-card__lines">
                        <span style="background: {{ $meta['swatch']['ink'] }}; opacity: 1"></span>
                        <span style="background: {{ $meta['swatch']['ink'] }}; opacity: 0.6"></span>
                        <span style="background: {{ $meta['swatch']['ink'] }}; opacity: 0.35"></span>
                    </div>
                    <span class="theme-card__accent"
                          style="background: {{ $meta['swatch']['accent'] }}"></span>
                </div>
                <div class="theme-card__meta">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold">{{ $meta['label'] }}</span>
                        <span class="theme-card__radio" aria-hidden="true"></span>
                    </div>
                    <p class="text-xs text-faint mt-1">{{ $meta['description'] }}</p>
                </div>
            </button>
        @endforeach
    </div>

    <p class="text-xs text-faint mt-4">
        El cambio se aplica al instante en este navegador y se persiste para el resto
        de tus sesiones.
    </p>
@endsection
