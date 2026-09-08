@extends('layouts.admin')

@section('title', 'Editar item')

@section('content')
    <div class="max-w-3xl space-y-6">

        <div>
            <h2 class="text-lg font-semibold tracking-tight">Editar item</h2>
            <p class="mt-1 text-sm text-gray-600">
                Item do menu <a href="{{ route('admin.menus.edit', $menu) }}"
                                class="font-medium text-gray-900 underline-offset-2 hover:underline">{{ $menu->name }}</a>.
                Alterar rótulo, destino ou estado mantém a ordem atual; trocar o item pai move o
                item para o fim do novo grupo.
            </p>
        </div>

        @include('admin.menus.partials.feedback')

        @include('admin.menus.partials.item-form', [
            'action' => route('admin.menus.items.update', [$menu, $item]),
            'method' => 'PUT',
            'submitLabel' => 'Salvar alterações',
        ])

    </div>
@endsection
