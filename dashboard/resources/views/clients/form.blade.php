@extends('layouts.app')
@section('title', $isNew ? 'Nuevo cliente' : 'Editar cliente')
@section('content')
    <div class="max-w-2xl">
        <h1 class="text-xl font-semibold tracking-tight mb-5">{{ $isNew ? 'Nuevo cliente' : 'Editar cliente' }}</h1>

        <form method="POST" action="{{ $isNew ? route('clients.store') : route('clients.update', $client) }}" class="card p-5 space-y-4">
            @csrf
            @unless ($isNew) @method('PATCH') @endunless

            <label class="label">
                <span>Nombre</span>
                <input type="text" name="name" required maxlength="128" value="{{ old('name', $client->name) }}"
                       class="input mt-1 @error('name') is-invalid @enderror" placeholder="ej. Acme S.L.">
                <x-field-error name="name" />
            </label>

            <div class="grid sm:grid-cols-2 gap-4">
                <label class="label">
                    <span>Empresa</span>
                    <input type="text" name="company" maxlength="128" value="{{ old('company', $client->company) }}" class="input mt-1">
                </label>
                <label class="label">
                    <span>Email</span>
                    <input type="email" name="email" maxlength="190" value="{{ old('email', $client->email) }}"
                           class="input mt-1 @error('email') is-invalid @enderror">
                    <x-field-error name="email" />
                </label>
                <label class="label">
                    <span>Teléfono</span>
                    <input type="text" name="phone" maxlength="64" value="{{ old('phone', $client->phone) }}" class="input mt-1">
                </label>
                <label class="label">
                    <span>Web</span>
                    <input type="text" name="website" maxlength="190" value="{{ old('website', $client->website) }}" class="input mt-1" placeholder="https://…">
                </label>
            </div>

            <label class="label">
                <span>Color</span>
                <input type="color" name="color" value="{{ old('color', $client->color ?? '#10b981') }}" class="mt-1 h-9 w-16 rounded">
                <x-field-error name="color" />
            </label>

            <label class="label">
                <span>Notas</span>
                <textarea name="notes" rows="3" maxlength="2000" class="textarea mt-1">{{ old('notes', $client->notes) }}</textarea>
            </label>

            <div class="flex justify-end gap-2">
                <a href="{{ route('clients.index') }}" class="btn-ghost">Cancelar</a>
                <button type="submit" class="btn">{{ $isNew ? 'Crear' : 'Guardar cambios' }}</button>
            </div>
        </form>
    </div>
@endsection
