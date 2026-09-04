@extends('layouts.admin')

@section('title', 'Tema e cores')

@section('content')
    <div class="max-w-2xl space-y-6">

        @include('admin.settings.partials.navigation')

        @if (session('status'))
            <div role="status" class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="text-sm font-medium text-red-800">Não foi possível salvar</p>
                <ul class="mt-1 space-y-1 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{--
            Preview server-side: reflete o que está persistido, não o que está
            sendo digitado. Acompanhar a digitação exigiria JavaScript, que está
            fora do escopo desta subfase.
        --}}
        <section aria-labelledby="tema-atual" class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
            <h2 id="tema-atual" class="text-lg font-semibold tracking-tight">Tema atual</h2>
            <p class="mt-2 text-sm text-gray-600">
                Cores em uso na loja. O preview é atualizado depois de salvar.
            </p>

            <dl class="mt-5 grid gap-4 sm:grid-cols-3">
                @foreach (['primary' => 'Primária', 'secondary' => 'Secundária', 'accent' => 'Destaque'] as $nome => $rotulo)
                    <div class="rounded-lg border border-gray-200 p-3">
                        <div class="h-12 w-full rounded-md border border-gray-200"
                             style="background-color: {{ $colors[$nome] }}"
                             aria-hidden="true"></div>
                        <dt class="mt-3 text-sm font-medium text-gray-900">{{ $rotulo }}</dt>
                        <dd class="mt-0.5 font-mono text-xs text-gray-600">{{ $colors[$nome] }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <form method="POST" action="{{ route('admin.settings.theme.update') }}"
              class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
            @csrf
            @method('PUT')

            <h2 class="text-lg font-semibold tracking-tight">Editar cores</h2>
            <p class="mt-2 text-sm text-gray-600">
                Informe cada cor no formato hexadecimal de seis dígitos, como
                <code class="font-mono">#2563EB</code>.
            </p>

            <div class="mt-6 space-y-5">
                @foreach (['primary' => 'Cor primária', 'secondary' => 'Cor secundária', 'accent' => 'Cor de destaque'] as $nome => $rotulo)
                    @php($campo = "{$nome}_color")
                    <div>
                        <label for="{{ $campo }}" class="block text-sm font-medium text-gray-900">
                            {{ $rotulo }}
                        </label>
                        <input
                            id="{{ $campo }}"
                            name="{{ $campo }}"
                            type="color"
                            value="{{ old($campo, $colors[$nome]) }}"
                            required
                            @error($campo) aria-invalid="true" aria-describedby="{{ $campo }}-erro" @enderror
                            class="mt-2 h-10 w-24 cursor-pointer rounded-lg border border-gray-300 p-1 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none @error($campo) border-red-400 @enderror"
                        >
                        @error($campo)
                            <p id="{{ $campo }}-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>

            <div class="mt-8">
                <button type="submit"
                        class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
                    Salvar tema
                </button>
            </div>
        </form>

    </div>
@endsection
