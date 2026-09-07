<?php

namespace App\Http\Requests\Admin;

/**
 * Mesmo contrato de entrada da criação.
 *
 * A edição não tem exceção a abrir: nenhum campo é único, nenhum depende do
 * próprio registro e `sort_order` continua fora do formulário. A diferença de
 * comportamento entre criar e editar vive no `BannerService` — reenviar a mesma
 * posição preserva a ordem, e trocar de posição move o banner para o fim do
 * destino —, não nesta validação.
 */
class UpdateBannerRequest extends StoreBannerRequest {}
