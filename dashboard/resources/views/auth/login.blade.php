<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>trackActivity · Equipo</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen flex items-center justify-center bg-surface-0 px-4">
    <form method="POST" action="{{ route('login.store') }}" class="card p-6 w-full max-w-sm space-y-4">
        @csrf
        <h1 class="text-lg font-semibold tracking-tight">Kanban de equipo</h1>

        @error('email')
            <p class="text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p>
        @enderror

        <div>
            <label class="label" for="email">Email</label>
            <input type="email" id="email" name="email" required autofocus
                   value="{{ old('email') }}" class="input">
        </div>
        <div>
            <label class="label" for="password">Contraseña</label>
            <input type="password" id="password" name="password" required class="input">
        </div>

        <button type="submit" class="btn w-full">Entrar</button>
    </form>
</body>
</html>
