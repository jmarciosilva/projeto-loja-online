@extends('layouts.app')

@section('title', config('app.name'))

@section('content')
    <div class="rounded-lg border border-gray-200 bg-white p-8">
        <p class="text-sm font-medium uppercase tracking-wide text-gray-500">
            Fase 1 concluída
        </p>

        <h1 class="mt-2 text-3xl font-semibold tracking-tight">
            {{ config('app.name') }}
        </h1>

        <p class="mt-4 max-w-2xl text-gray-600">
            Ambiente de desenvolvimento no ar: Laravel {{ app()->version() }} sobre
            PHP {{ PHP_VERSION }}, com Livewire, Vite e TailwindCSS. Nenhuma
            funcionalidade de e-commerce foi implementada ainda — o catálogo, o
            carrinho e o painel administrativo são o escopo das próximas fases.
        </p>

        <dl class="mt-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
            @foreach ([
                'Ambiente' => app()->environment(),
                'Cache' => config('cache.default'),
                'Fila' => config('queue.default'),
                'Banco' => config('database.default'),
            ] as $rotulo => $valor)
                <div class="rounded-md bg-gray-50 px-4 py-3">
                    <dt class="text-xs uppercase tracking-wide text-gray-500">{{ $rotulo }}</dt>
                    <dd class="mt-1 font-medium">{{ $valor }}</dd>
                </div>
            @endforeach
        </dl>

        <p class="mt-8 text-sm text-gray-500">
            Verificação de dependências em
            <a href="{{ route('health') }}" class="underline hover:text-gray-900">/health</a>.
        </p>
    </div>
@endsection
