@extends('layouts.admin')

@section('title', 'Identidade visual')

@section('content')
    <div class="max-w-2xl space-y-6">

        @include('admin.settings.partials.navigation')

        @if (session('status'))
            <div role="status" class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-4">
                <p class="text-sm font-medium text-red-800">Não foi possível salvar</p>
                <ul class="mt-1 space-y-1 text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{--
            Preview server-side do que está persistido. A URL vem sempre da
            própria mídia — nada de remontar caminho aqui.
        --}}
        <section aria-labelledby="identidade-atual" class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
            <h2 id="identidade-atual" class="text-lg font-semibold tracking-tight">Identidade atual</h2>
            <p class="mt-2 text-sm text-gray-600">
                Imagens em uso na loja. O preview é atualizado depois de salvar.
            </p>

            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
                <div class="rounded-lg border border-gray-200 p-3">
                    <dt class="text-sm font-medium text-gray-900">Logo</dt>
                    <dd class="mt-2">
                        @if ($logo)
                            {{-- max-h com w-auto: o preview reduz sem distorcer. --}}
                            <img src="{{ $identity->url($logo) }}"
                                 alt="Logo configurada: {{ $logo->original_name }}"
                                 width="{{ $logo->width }}" height="{{ $logo->height }}"
                                 class="max-h-16 w-auto max-w-full">
                            <p class="mt-2 text-xs text-gray-600">
                                {{ $logo->original_name }} — {{ $logo->width }} × {{ $logo->height }} px
                            </p>
                        @else
                            <p class="text-sm text-gray-500">Nenhuma logo configurada.</p>
                        @endif
                    </dd>
                </div>

                <div class="rounded-lg border border-gray-200 p-3">
                    <dt class="text-sm font-medium text-gray-900">Favicon</dt>
                    <dd class="mt-2">
                        @if ($favicon)
                            <img src="{{ $identity->url($favicon) }}"
                                 alt="Favicon configurado: {{ $favicon->original_name }}"
                                 width="{{ $favicon->width }}" height="{{ $favicon->height }}"
                                 class="size-8 object-contain">
                            <p class="mt-2 text-xs text-gray-600">
                                {{ $favicon->original_name }} — {{ $favicon->width }} × {{ $favicon->height }} px
                            </p>
                        @else
                            <p class="text-sm text-gray-500">Nenhum favicon configurado.</p>
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

        <form method="POST" action="{{ route('admin.settings.identity.update') }}"
              class="rounded-xl border border-gray-200 bg-white p-6 sm:p-8">
            @csrf
            @method('PUT')

            <h2 class="text-lg font-semibold tracking-tight">Escolher imagens</h2>
            <p class="mt-2 text-sm text-gray-600">
                As imagens vêm da <a href="{{ route('admin.media.index') }}" class="font-medium underline">biblioteca de
                mídia</a>. Esta tela não envia arquivos: envie primeiro na biblioteca e selecione aqui.
            </p>

            @if ($availableMedia->isEmpty())
                <p class="mt-6 rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-600">
                    A biblioteca de mídia ainda está vazia. Envie uma imagem para poder escolher a logo e o favicon.
                </p>
            @endif

            <div class="mt-6 space-y-5">
                @php
                    $campos = [
                        'logo_media_id' => ['rotulo' => 'Logo', 'atual' => $logoMediaId, 'ajuda' => 'Exibida no topo da loja.'],
                        'favicon_media_id' => ['rotulo' => 'Favicon', 'atual' => $faviconMediaId, 'ajuda' => 'Ícone da aba do navegador. Use um PNG pequeno e quadrado.'],
                    ];
                @endphp

                @foreach ($campos as $campo => $config)
                    <div>
                        <label for="{{ $campo }}" class="block text-sm font-medium text-gray-900">
                            {{ $config['rotulo'] }}
                        </label>
                        <select
                            id="{{ $campo }}"
                            name="{{ $campo }}"
                            @error($campo) aria-invalid="true" aria-describedby="{{ $campo }}-erro" @enderror
                            class="mt-2 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none @error($campo) border-red-400 @enderror"
                        >
                            <option value="">Nenhuma imagem</option>
                            @foreach ($availableMedia as $media)
                                {{-- O value é sempre Media.id: o path nunca é exposto como identidade. --}}
                                <option value="{{ $media->id }}"
                                    @selected((int) old($campo, $config['atual']) === $media->id)>
                                    #{{ $media->id }} — {{ $media->original_name }} ({{ $media->width }} × {{ $media->height }} px)
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-gray-500">{{ $config['ajuda'] }}</p>
                        @error($campo)
                            <p id="{{ $campo }}-erro" class="mt-2 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>

            <div class="mt-8">
                <button type="submit"
                        class="rounded-lg bg-gray-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-700 focus-visible:ring-2 focus-visible:ring-gray-900 focus-visible:ring-offset-2 focus-visible:outline-none">
                    Salvar identidade visual
                </button>
            </div>
        </form>

    </div>
@endsection
