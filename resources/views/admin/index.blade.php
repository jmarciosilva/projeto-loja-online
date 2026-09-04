<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 px-4 py-12 text-gray-900">
    <main class="mx-auto max-w-3xl rounded-lg bg-white p-8 shadow">
        <h1 class="text-2xl font-semibold">Área administrativa</h1>
        <p class="mt-2 text-gray-600">Acesso autenticado confirmado.</p>

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button type="submit" class="rounded bg-gray-900 px-4 py-2 font-medium text-white">Sair</button>
        </form>
    </main>
</body>
</html>
