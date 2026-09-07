@extends('layouts.admin')

@section('title', 'Editar banner')

@section('content')
    <div class="max-w-3xl space-y-6">

        <div>
            <h2 class="text-lg font-semibold tracking-tight">Editar banner</h2>
            <p class="mt-1 text-sm text-gray-600">
                Alterar nome, imagem, link, texto alternativo ou estado mantém a ordem atual.
            </p>
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

        @include('admin.banners.partials.form', [
            'action' => route('admin.banners.update', $banner),
            'method' => 'PUT',
            'submitLabel' => 'Salvar alterações',
        ])

    </div>
@endsection
