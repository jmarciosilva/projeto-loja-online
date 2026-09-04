<header class="border-b border-gray-200 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8">

        <h1 class="text-base font-semibold tracking-tight">
            @yield('title', 'Área administrativa')
        </h1>

        <div class="flex items-center gap-3">
            @auth
                <span class="hidden text-sm text-gray-600 sm:inline">
                    {{ auth()->user()->name }}
                </span>
            @endauth

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
                    Sair
                </button>
            </form>
        </div>

    </div>
</header>
