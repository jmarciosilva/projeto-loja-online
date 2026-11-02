@extends('layouts.admin')
@section('title', 'Nova categoria')
@section('content')
<div class="max-w-3xl space-y-5"><a class="text-sm underline" href="{{ route('admin.categories.index') }}">← Categorias</a><h2 class="text-lg font-semibold">Nova categoria</h2>@include('admin.categories.partials.form', ['action' => route('admin.categories.store'), 'method' => 'POST', 'submitLabel' => 'Criar categoria'])</div>
@endsection
