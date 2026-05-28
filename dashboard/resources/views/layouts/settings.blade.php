{{-- Layout para todas las páginas bajo "Configuración".

     Hereda del layout global (sidebar, header, theme…) y añade un
     mini-sidebar a la izquierda con las subsecciones. El contenido
     concreto vive en @section('settings-content').

     Cualquier vista de ajustes hace:
         @extends('layouts.settings')
         @section('title', 'Mi sección')
         @section('settings-content') ... @endsection --}}
@extends('layouts.app')

@section('content')
    <div class="settings-shell flex flex-col lg:flex-row gap-6">
        @include('layouts.partials.settings-nav')

        <section class="flex-1 min-w-0">
            @yield('settings-content')
        </section>
    </div>
@endsection
