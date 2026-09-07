{{--
    Banners de destaque da vitrine.

    Recebe `$heroBanners` já filtrado e ordenado pelo `BannerService`: a view
    não consulta `Banner`, não conhece `is_active` e não reordena nada.

    Sem banners ativos, **nada** é emitido — nem a seção vazia, nem uma moldura
    sem conteúdo.

    A mídia é conferida antes de renderizar pelo mesmo motivo da listagem
    administrativa: a FK protege o banco, mas a vitrine prefere omitir um item
    a estourar caso o relacionamento não resolva.
--}}
@if ($heroBanners->isNotEmpty())
    <section aria-label="Destaques" class="mb-8 space-y-4">
        @foreach ($heroBanners as $banner)
            @continue($banner->media === null)

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                {{--
                    O link é opcional: sem `link_url`, a imagem é renderizada
                    sem âncora — envolver tudo num `<a>` inerte criaria um alvo
                    de clique que não leva a lugar nenhum.

                    A URL já passou pelo contrato autoritativo do
                    `BannerService`, que só aceita caminho interno ou HTTP(S)
                    com host válido. Aqui ela é apenas escapada pelo Blade; não
                    há `target`, `rel` automático nem JavaScript.
                --}}
                @if ($banner->link_url !== null)
                    <a href="{{ $banner->link_url }}" class="block">
                        @include('partials.banner-image', ['banner' => $banner, 'media' => $media])
                    </a>
                @else
                    @include('partials.banner-image', ['banner' => $banner, 'media' => $media])
                @endif
            </div>
        @endforeach
    </section>
@endif
