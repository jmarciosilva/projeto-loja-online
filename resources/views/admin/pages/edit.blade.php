@extends('layouts.admin')

@section('title', 'Editar página')

@section('content')
    <div class="max-w-3xl space-y-6">

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">Editar página</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Alterar apenas o título mantém o endereço já publicado.
                </p>
            </div>
            {{-- Pela identidade, nunca pelo slug: o endereço muda, o id não. --}}
            <a href="{{ route('admin.pages.preview', $page) }}"
               class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-900 transition-colors hover:bg-gray-100 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
                Preview
            </a>
        </div>

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

        @include('admin.pages.partials.form', [
            'action' => route('admin.pages.update', $page),
            'method' => 'PUT',
            'submitLabel' => 'Salvar alterações',
        ])

    </div>
@endsection
