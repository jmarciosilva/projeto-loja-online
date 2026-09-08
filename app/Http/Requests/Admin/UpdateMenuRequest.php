<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Entrada do formulário de edição de menu.
 *
 * O contrato **difere** do de criação, e por isso não herda dele: `code` não
 * está aqui. Ele é a identidade técnica pela qual o consumidor público encontra
 * o menu e é imutável depois da criação — validá-lo na edição daria a entender
 * que existe algo a decidir.
 *
 * A tela mostra o código como texto informativo, sem input. Um `code` forçado
 * no POST também não chega ao domínio: o Controller monta o payload a partir
 * desta validação, e o `MenuService` recusaria a alteração de qualquer forma.
 */
class UpdateMenuRequest extends FormRequest
{
    /**
     * Durante a Fase 2 o middleware `auth` da rota é a única barreira. Papéis e
     * permissões são escopo da Fase 3.
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
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
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
        ];
    }
}
