@extends('layouts.admin')
@section('title', 'Categorias')
@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-lg font-semibold">Categorias</h2><p class="mt-1 text-sm text-gray-600">Organize os produtos da loja.</p></div><a href="{{ route('admin.categories.create') }}" class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white">Nova categoria</a></div>
    @if (session('status'))<div role="status" class="rounded-lg bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>@endif
    @if ($categories->isEmpty())<div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center"><p>Nenhuma categoria cadastrada.</p><a class="mt-3 inline-block font-semibold underline" href="{{ route('admin.categories.create') }}">Criar a primeira categoria</a></div>
    @else
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white"><table class="w-full text-left text-sm"><thead class="border-b text-xs text-gray-500"><tr><th class="px-4 py-3">Categoria</th><th class="px-4 py-3">Endereço</th><th class="px-4 py-3">Produtos</th><th class="px-4 py-3">Estado</th><th class="px-4 py-3 text-right">Ações</th></tr></thead><tbody class="divide-y divide-gray-100">
    @foreach ($categories as $category)<tr><td class="px-4 py-3 font-medium">{{ $category->parent?->name ? $category->parent->name.' › ' : '' }}{{ $category->name }}</td><td class="px-4 py-3 text-gray-600">{{ $category->slug }}</td><td class="px-4 py-3">{{ $category->products_count }}</td><td class="px-4 py-3">{{ $category->is_active ? 'Ativa' : 'Inativa' }}</td><td class="px-4 py-3"><div class="flex justify-end gap-3"><a class="font-medium underline" href="{{ route('admin.categories.edit', $category) }}">Editar</a><form method="POST" action="{{ route('admin.categories.toggle', $category) }}">@csrf<button class="font-medium underline">{{ $category->is_active ? 'Desativar' : 'Ativar' }}</button></form><form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Excluir esta categoria? Os produtos serão mantidos, mas deixarão de aparecer na vitrine.')">@csrf @method('DELETE')<button class="font-medium text-red-700 underline">Excluir</button></form></div></td></tr>@endforeach
    </tbody></table></div>@if ($categories->hasPages()){{ $categories->links() }}@endif
    @endif
</div>
@endsection
