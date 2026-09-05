{{--
    Corpo da página, compartilhado pela exibição pública e pelo preview
    administrativo. É o que garante que preview e público não divirjam.

    Recebe: $page e $content.

    `$content` é o HTML já sanitizado pelo PageContentRenderer — a única fonte
    autorizada para impressão sem escape aqui. `$page->content` cru jamais é
    impresso: ele é Markdown escrito no editor, e imprimí-lo devolveria o vetor
    de script que o renderer existe para remover.
--}}
<article>
    <h1 class="text-3xl font-semibold tracking-tight">{{ $page->title }}</h1>

    <div class="page-content mt-6">
        {!! $content !!}
    </div>
</article>
