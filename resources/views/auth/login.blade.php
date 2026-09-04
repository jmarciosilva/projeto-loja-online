<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-gray-100 px-4">
    <main class="w-full max-w-md rounded-lg bg-white p-8 shadow">
        <h1 class="text-2xl font-semibold text-gray-900">Entrar</h1>

        @if ($errors->any())
            <div class="mt-4 rounded border border-red-200 bg-red-50 p-3 text-sm text-red-700" role="alert">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-4">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">E-mail</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="mt-1 block w-full rounded border-gray-300 shadow-sm">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">Senha</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="mt-1 block w-full rounded border-gray-300 shadow-sm">
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input name="remember" type="checkbox" class="rounded border-gray-300">
                Lembrar de mim
            </label>

            <button type="submit" class="w-full rounded bg-gray-900 px-4 py-2 font-medium text-white">
                Entrar
            </button>
        </form>
    </main>
</body>
</html>
