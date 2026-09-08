<?php

namespace App\Http\Requests\Admin;

use App\Services\MenuService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada do formulário de criação de menu.
 *
 * O Form Request é **barreira antecipada**, não camada autoritativa: ele
 * devolve o erro ao formulário, com o campo destacado e o input preservado,
 * enquanto o `MenuService` continua garantindo as mesmas regras para qualquer
 * consumidor — inclusive fora do HTTP.
 *
 * Por isso o contrato do `code` não é reescrito aqui: quem responde é o próprio
 * serviço, com a normalização usada na gravação. Duplicar a expressão regular
 * faria a interface e o domínio divergirem na primeira mudança.
 */
class StoreMenuRequest extends FormRequest
{
    /**
     * Durante a Fase 2 qualquer usuário autenticado administra os menus; o
     * middleware `auth` da rota é a única barreira. Papéis e permissões são
     * escopo da Fase 3 e não devem ser antecipados aqui.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // `unique` antecipa a colisão como erro de formulário; o `UNIQUE`
            // da coluna continua sendo a barreira final, e o serviço recusa a
            // duplicata como erro de domínio para quem não vem do HTTP.
            'code' => ['required', 'string', 'max:64', 'unique:menus,code', $this->codeIsSupported()],
            // A caixa de seleção viaja com um campo oculto, então o valor chega
            // sempre como "0" ou "1"; `nullable` cobre um POST sem o campo.
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Antecipa o contrato do `code` para dar erro no formulário.
     *
     * O código **não** é gerado a partir do nome nem saneado: um valor fora do
     * formato é recusado, e não corrigido — é a chave técnica pela qual o
     * consumidor público encontrará o menu.
     */
    private function codeIsSupported(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! app(MenuService::class)->isSupportedCode($value)) {
                $fail('Use apenas letras minúsculas, números e hífen simples entre segmentos — por exemplo, menu-principal.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'code' => 'código',
            'is_active' => 'estado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do menu.',
            'code.required' => 'Informe o código do menu.',
            'code.unique' => 'Já existe um menu com este código.',
        ];
    }
}
