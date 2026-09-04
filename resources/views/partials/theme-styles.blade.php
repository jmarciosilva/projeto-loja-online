{{--
    As cores do tema são dados de runtime: vêm do banco e mudam sem rebuild.
    Por isso são emitidas aqui, no HTML renderizado, em vez de entrarem no
    bloco @theme do Tailwind — aquele é compilado pelo Vite e exigiria
    `npm run build` a cada alteração salva no painel.

    O contrato #RRGGBB validado no Form Request é o que mantém seguro
    interpolar estes valores dentro de <style>.
--}}
<style>
    :root {
        --color-primary: {{ $themeColors['primary'] }};
        --color-secondary: {{ $themeColors['secondary'] }};
        --color-accent: {{ $themeColors['accent'] }};
    }
</style>
