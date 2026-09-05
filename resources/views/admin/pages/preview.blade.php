@extends('layouts.admin')

@section('title'){{ $page->title }}@endsection

@section('content')
    <div class="max-w-3xl space-y-6">

        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">Preview</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Conteúdo como aparecerá na página pública.
                </p>
            </div>
            <a href="{{ route('admin.pages.edit', $page) }}"
               class="text-sm font-medium text-gray-600 hover:text-gray-900">
                Voltar para a edição
            </a>
        </div>

        @if ($page->status !== \App\Enums\PageStatus::Published)
            <div role="status" class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Esta página está como <strong>{{ $statuses[$page->status->value] }}</strong> e
                ainda não é acessível publicamente.
            </div>
        @endif

        {{-- O mesmo parcial da página pública: preview que renderiza por conta
             própria deixa de provar o que promete. --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
            @include('pages.partials.content')
        </div>

    </div>
@endsection
