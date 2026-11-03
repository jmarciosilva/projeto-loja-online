<div class="space-y-6">
    <a class="text-sm underline" href="{{ route('admin.products.edit', $product) }}">← Voltar ao produto</a>
    <div><h2 class="text-xl font-semibold">Estoque: {{ $product->name }}</h2><p class="mt-1 text-sm text-gray-600">Produto: {{ $product->sku }} · Estoque disponível = físico menos reservado.</p></div>
    @php($stock = $product->stock)
    @if ($stock)
        <p class="rounded-lg bg-gray-50 p-3 text-sm">Situação: {{ ! $stock->stock_control_enabled ? 'Disponibilidade não informada' : ($stock->availableQuantity() === 0 ? 'Esgotado' : ($stock->availableQuantity() <= $stock->minimum_quantity ? 'Estoque baixo' : ($stock->availableQuantity() === 1 ? 'Última unidade' : 'Disponível'))) }}</p>
        <section class="grid gap-4 rounded-xl border bg-white p-5 sm:grid-cols-2 lg:grid-cols-4" aria-label="Resumo do estoque">
            <p>Estoque físico: <strong>{{ $stock->stock_control_enabled ? $stock->quantity_on_hand : 'Não informado' }}</strong></p>
            <p>Reservado: <strong>{{ $stock->stock_control_enabled ? $stock->quantity_reserved : '—' }}</strong></p>
            <p>Disponível: <strong>{{ $stock->stock_control_enabled ? $stock->availableQuantity() : 'Não informado' }}</strong></p>
            <p>Estoque mínimo: <strong>{{ $stock->stock_control_enabled ? $stock->minimum_quantity : '—' }}</strong></p>
        </section>
    @else
        <p class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm"><strong>Situação: Disponibilidade não informada.</strong> Estoque não configurado. Nenhuma quantidade foi inferida para este produto.</p>
    @endif

    @can('manageStock', $product)
        <section class="rounded-xl border bg-white p-5 sm:p-7">
            <h3 class="font-semibold">{{ $stock?->stock_control_enabled ? 'Registrar movimentação' : 'Configurar estoque inicial' }}</h3>
            <form method="POST" action="{{ route('admin.products.stock.store', $product) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                @if (! $stock || ! $stock->stock_control_enabled)
                    <input type="hidden" name="mode" value="initial">
                    <div><label for="stock_control_enabled" class="block text-sm font-medium">Controle de estoque</label><select id="stock_control_enabled" name="stock_control_enabled" class="mt-1 w-full rounded-lg border px-3 py-2.5"><option value="1" @selected(old('stock_control_enabled', $stock ? '1' : '0') === '1')>Controlar saldo físico</option><option value="0" @selected(old('stock_control_enabled', $stock ? '1' : '0') === '0')>Não controlar estoque</option></select></div>
                    <div><label for="quantity" class="block text-sm font-medium">Saldo físico inicial</label><input id="quantity" name="quantity" type="number" min="0" max="1000000" value="{{ old('quantity', 0) }}" class="mt-1 w-full rounded-lg border px-3 py-2.5"></div>
                    <div><label for="minimum_quantity" class="block text-sm font-medium">Estoque mínimo</label><input id="minimum_quantity" name="minimum_quantity" type="number" min="0" max="1000000" value="{{ old('minimum_quantity', 0) }}" class="mt-1 w-full rounded-lg border px-3 py-2.5"></div>
                @else
                    <input type="hidden" name="mode" value="movement">
                    <div><label for="type" class="block text-sm font-medium">Tipo de movimentação</label><select id="type" name="type" class="mt-1 w-full rounded-lg border px-3 py-2.5"><option value="entry">Entrada</option><option value="manual_exit">Saída manual</option><option value="positive_adjustment">Ajuste positivo</option><option value="negative_adjustment">Ajuste negativo</option></select></div>
                    <div><label for="quantity" class="block text-sm font-medium">Quantidade</label><input id="quantity" name="quantity" type="number" min="1" max="1000000" value="{{ old('quantity') }}" class="mt-1 w-full rounded-lg border px-3 py-2.5"></div>
                    <div class="sm:col-span-2"><label for="reason" class="block text-sm font-medium">Motivo</label><input id="reason" name="reason" maxlength="500" required value="{{ old('reason') }}" class="mt-1 w-full rounded-lg border px-3 py-2.5"></div>
                @endif
                @error('stock')<p class="sm:col-span-2 text-sm text-red-700">{{ $message }}</p>@enderror
                @foreach (['mode','stock_control_enabled','quantity','minimum_quantity','type','reason'] as $field) @error($field)<p class="text-sm text-red-700">{{ $message }}</p>@enderror @endforeach
                <button class="w-fit rounded-lg bg-orange-500 px-4 py-2.5 font-semibold text-slate-950">{{ $stock?->stock_control_enabled ? 'Registrar movimentação' : 'Salvar configuração' }}</button>
            </form>
        </section>
    @endcan

    <section class="overflow-hidden rounded-xl border bg-white">
        <h3 class="p-5 font-semibold">Histórico de movimentações</h3>
        @if ($movements->isEmpty())<p class="px-5 pb-5 text-sm text-gray-600">Nenhuma movimentação registrada.</p>
        @else<div class="overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="bg-gray-50"><tr><th class="px-4 py-3">Data</th><th class="px-4 py-3">Tipo</th><th class="px-4 py-3">Variação</th><th class="px-4 py-3">Saldo</th><th class="px-4 py-3">Motivo</th><th class="px-4 py-3">Responsável</th></tr></thead><tbody>@foreach ($movements as $movement)<tr class="border-t"><td class="px-4 py-3">{{ $movement->created_at->format('d/m/Y H:i') }}</td><td class="px-4 py-3">{{ str($movement->type)->replace('_', ' ')->ucfirst() }}</td><td class="px-4 py-3">{{ $movement->quantity_delta > 0 ? '+' : '' }}{{ $movement->quantity_delta }}</td><td class="px-4 py-3">{{ $movement->quantity_before }} → {{ $movement->quantity_after }}</td><td class="px-4 py-3">{{ $movement->reason }}</td><td class="px-4 py-3">{{ $movement->user?->name ?? 'Usuário removido' }}</td></tr>@endforeach</tbody></table></div>@endif
    </section>
</div>
