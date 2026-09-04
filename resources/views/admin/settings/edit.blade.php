@extends('layouts.admin')

@section('title', 'Configurações gerais')

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

        <form method="POST" action="{{ route('admin.settings.update') }}"
              class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
            @csrf
            @method('PUT')

            <h2 class="text-lg font-semibold tracking-tight">Configurações gerais</h2>
            <p class="mt-2 text-sm text-gray-600">
                Dados institucionais da loja, usados nas páginas públicas e no
                contato com clientes.
            </p>

            <div class="mt-6 space-y-5">

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-900">
                        Nome da loja
                    </label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        value="{{ old('name', $settings['name']) }}"
                        required
                        maxlength="150"
                        @error('name') aria-invalid="true" aria-describedby="name-erro" @enderror
                        class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('name') border-red-400 @enderror"
                    >
                    @error('name')
                        <p id="name-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="support_email" class="block text-sm font-medium text-gray-900">
                        Email de suporte
                    </label>
                    <input
                        id="support_email"
                        name="support_email"
                        type="email"
                        value="{{ old('support_email', $settings['support_email']) }}"
                        maxlength="254"
                        autocomplete="email"
                        @error('support_email') aria-invalid="true" aria-describedby="support-email-erro" @enderror
                        class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('support_email') border-red-400 @enderror"
                    >
                    @error('support_email')
                        <p id="support-email-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-900">
                        Telefone
                    </label>
                    <input
                        id="phone"
                        name="phone"
                        type="text"
                        value="{{ old('phone', $settings['phone']) }}"
                        maxlength="30"
                        autocomplete="tel"
                        @error('phone') aria-invalid="true" aria-describedby="phone-erro" @enderror
                        class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('phone') border-red-400 @enderror"
                    >
                    @error('phone')
                        <p id="phone-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="address" class="block text-sm font-medium text-gray-900">
                        Endereço
                    </label>
                    <textarea
                        id="address"
                        name="address"
                        rows="3"
                        maxlength="500"
                        @error('address') aria-invalid="true" aria-describedby="address-erro" @enderror
                        class="mt-2 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm focus:border-gray-900 focus:ring-2 focus:ring-gray-900/10 focus:outline-none @error('address') border-red-400 @enderror"
                    >{{ old('address', $settings['address']) }}</textarea>
                    @error('address')
                        <p id="address-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            <div class="mt-8">
                <button type="submit"
                        class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
                    Salvar configurações
                </button>
            </div>
        </form>

    </div>
@endsection
