{{--
    Imagem de um banner público.

    Recebe `$banner` e o `$media` (MediaService), de onde sai a URL: o banner
    referencia `Media.id` e nunca guarda caminho nem URL.

    O texto alternativo é o `alt_text` do **banner** — nunca o `original_name`
    da mídia, que é metadado administrativo e não descreve a imagem para quem
    usa leitor de tela. `name` também não aparece: ele identifica o banner no
    painel, não é título público.

    `width` e `height` vêm da própria mídia, que os grava NOT NULL, e existem
    para reservar o espaço antes do carregamento.
--}}
<img src="{{ $media->url($banner->media) }}"
     alt="{{ $banner->alt_text }}"
     width="{{ $banner->media->width }}"
     height="{{ $banner->media->height }}"
     class="h-auto w-full">
