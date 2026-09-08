@extends('layouts.admin')

@section('title', 'Novo menu')

@section('content')
    <div class="max-w-3xl space-y-6">

        <div>
            <h2 class="text-lg font-semibold tracking-tight">Novo menu</h2>
            <p class="mt-1 text-sm text-gray-600">
                O menu nasce inativo e sem itens. Os itens são adicionados na tela de edição.
            </p>
        </div>

        @include('admin.menus.partials.feedback')

        @include('admin.menus.partials.menu-form', [
            'action' => route('admin.menus.store'),
            'method' => 'POST',
            'submitLabel' => 'Criar menu',
        ])

    </div>
@endsection
