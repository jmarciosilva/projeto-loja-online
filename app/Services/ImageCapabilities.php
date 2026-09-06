<?php

namespace App\Services;

/**
 * Capacidades reais do processador de imagens do runtime.
 *
 * O WebP não é uma decisão de configuração: ele depende de o GD ter sido
 * compilado com `--with-webp`. Um ambiente sem esse suporte falha ao codificar
 * com `Call to undefined function imagewebp()` — um `Error` do PHP, e não uma
 * exceção do Intervention, que nenhuma validação de formulário interceptaria.
 *
 * Por isso a capacidade é lida do próprio runtime, e não de `.env` ou de um
 * arquivo de configuração: uma flag manual poderia afirmar um suporte que a
 * imagem não tem, e o erro só apareceria no meio de um upload.
 *
 * A classe existe como objeto — em vez de um método estático — para que os
 * testes possam substituí-la no container e exercitar o caminho em que o
 * runtime **não** suporta WebP, sem depender da máquina que roda a suíte.
 */
class ImageCapabilities
{
    /**
     * O GD desta imagem foi compilado com suporte a WebP?
     */
    public function supportsWebp(): bool
    {
        return (gd_info()['WebP Support'] ?? false) === true;
    }
}
