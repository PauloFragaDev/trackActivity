@extends('layouts.app')
@section('title', $isNew ? 'Nuevo cliente' : 'Editar cliente')
@section('content')
    <div class="max-w-2xl">
        <h1 class="text-xl font-semibold tracking-tight mb-5">{{ $isNew ? 'Nuevo cliente' : 'Editar cliente' }}</h1>

        <form method="POST" action="{{ $isNew ? route('clients.store') : route('clients.update', $client) }}" class="card p-5 space-y-4">
            @csrf
            @unless ($isNew) @method('PATCH') @endunless

            @include('clients.partials.form-fields')

            <div class="flex justify-end gap-2">
                <a href="{{ route('clients.index') }}" class="btn-ghost">Cancelar</a>
                <button type="submit" class="btn">{{ $isNew ? 'Crear' : 'Guardar cambios' }}</button>
            </div>
        </form>
    </div>
@endsection
