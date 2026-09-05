@extends('layouts.app')

{{-- Bloco em vez de @section('title', ...): @yield imprime sem escape, e o
     título vem de conteúdo editável. Aqui o {{ }} escapa. --}}
@section('title'){{ $page->meta_title ?: $page->title }}@endsection

@section('meta')
    @if (filled($page->meta_description))
        <meta name="description" content="{{ $page->meta_description }}">
    @endif
@endsection

@section('content')
    <div class="rounded-lg border border-gray-200 bg-white p-8">
        @include('pages.partials.content')
    </div>
@endsection
