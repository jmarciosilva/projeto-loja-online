@extends('layouts.admin')

@section('title', 'Mídia')

@section('content')
    <div class="space-y-6">
        <div><h2 class="text-lg font-semibold tracking-tight">Mídia</h2><p class="mt-1 text-sm text-gray-600">Biblioteca de imagens da loja.</p></div>
        @if (session('status')) <div role="status" class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div> @endif
        @if (session('error')) <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ session('error') }}</div> @endif
        <form method="POST" action="{{ route('admin.media.store') }}" enctype="multipart/form-data" class="rounded-xl border border-gray-200 bg-white p-4">
            @csrf
            <label for="file" class="block text-sm font-medium text-gray-900">Enviar imagem</label>
            <div class="mt-2 flex flex-wrap items-center gap-3"><input id="file" name="file" type="file" accept="image/jpeg,image/png,image/webp" required><button class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white">Enviar</button></div>
            @error('file') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
        </form>
        @if ($media->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-300 bg-white p-10 text-center text-sm">Nenhuma mídia cadastrada.</div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($media as $item)
                    <article class="overflow-hidden rounded-xl border border-gray-200 bg-white"><img src="{{ $service->url($item) }}" alt="" class="h-40 w-full object-contain bg-gray-50"><div class="space-y-1 p-4 text-sm"><p class="truncate font-medium">{{ $item->original_name }}</p><p>{{ $item->width }} × {{ $item->height }} · {{ $item->mime_type }}</p><p>{{ number_format($item->size / 1024, 1, ',', '.') }} KB · {{ $item->created_at?->format('d/m/Y H:i') }}</p><form method="POST" action="{{ route('admin.media.destroy', $item) }}">@csrf @method('DELETE')<button class="mt-2 font-medium text-red-700">Excluir</button></form></div></article>
                @endforeach
            </div>
            @if ($media->hasPages()) <div>{{ $media->links() }}</div> @endif
        @endif
    </div>
@endsection
