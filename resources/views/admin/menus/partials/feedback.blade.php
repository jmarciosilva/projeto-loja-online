{{--
    Retorno das operações de menu, no mesmo formato já usado por páginas e
    banners.

    O bloco vermelho junta a validação antecipada dos Form Requests e as
    recusas de domínio traduzidas pelo Controller: para quem administra, as duas
    são a mesma coisa — algo não foi salvo, e aqui está o motivo. Nenhuma
    exceção chega crua à tela, e nenhuma falha é silenciada.
--}}
@if (session('status'))
    <div role="status" class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4">
        <p class="text-sm font-medium text-red-800">Não foi possível concluir a ação</p>
        <ul class="mt-1 space-y-1 text-sm text-red-700">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
