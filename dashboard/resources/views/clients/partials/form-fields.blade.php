{{-- Campos del formulario de cliente. Compartido entre la página de edición
     (clients/form.blade.php) y el modal de alta (clients/index.blade.php).
     Espera $client (modelo, en blanco para alta). --}}
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
