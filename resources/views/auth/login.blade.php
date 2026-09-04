<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased">

    <div class="flex min-h-full flex-col justify-center px-4 py-12 sm:px-6 lg:px-8">
        <main class="mx-auto w-full max-w-md">

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8">

                <div class="flex size-11 items-center justify-center rounded-xl bg-gray-900">
                    {{-- Ícone decorativo: sinaliza área restrita sem representar uma marca. --}}
                    <svg class="size-5 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M10 1a4 4 0 0 0-4 4v3H5.5A1.5 1.5 0 0 0 4 9.5v7A1.5 1.5 0 0 0 5.5 18h9a1.5 1.5 0 0 0 1.5-1.5v-7A1.5 1.5 0 0 0 14.5 8H14V5a4 4 0 0 0-4-4Zm2.5 7V5a2.5 2.5 0 0 0-5 0v3h5Z" clip-rule="evenodd" />
                    </svg>
                </div>

                <h1 class="mt-6 text-2xl font-semibold tracking-tight text-gray-900">
                    Área administrativa
                </h1>

                <p class="mt-2 text-sm text-gray-600">
                    Entre com suas credenciais para continuar.
                </p>

                @if ($errors->any())
                    <div role="alert" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4">
                        <p class="text-sm font-medium text-red-800">
                            Não foi possível entrar
                        </p>
                        <ul class="mt-1 space-y-1 text-sm text-red-700">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-900">
                            E-mail
                        </label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="email"
                            @error('email') aria-invalid="true" aria-describedby="email-erro" @enderror
                            class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('email') border-red-400 @enderror"
                        >
                        @error('email')
                            <p id="email-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-900">
                            Senha
                        </label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                            @error('password') aria-invalid="true" aria-describedby="senha-erro" @enderror
                            class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('password') border-red-400 @enderror"
                        >
                        @error('password')
                            <p id="senha-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center">
                        <input
                            id="remember"
                            name="remember"
                            type="checkbox"
                            class="size-4 rounded border-gray-300 text-gray-900 focus:ring-2 focus:ring-gray-900/20"
                        >
                        <label for="remember" class="ml-2 text-sm text-gray-700 select-none">
                            Lembrar-me neste dispositivo
                        </label>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        Entrar
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-xs text-gray-500">
                Acesso restrito a pessoas autorizadas.
            </p>

        </main>
    </div>

</body>
</html>
