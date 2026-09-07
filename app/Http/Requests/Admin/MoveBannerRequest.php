<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entrada da ordenação administrativa.
 *
 * O payload é deliberadamente mínimo — o banner vem da rota e a direção é um
 * de dois valores. Não há lista de ids nem número de ordem: quanto menor a
 * superfície, menos há para validar e menos estados inválidos existem para
 * recusar.
 */
class MoveBannerRequest extends FormRequest
{
    /**
     * Direção que sobe o banner na lista da sua posição.
     */
    public const UP = 'up';

    /**
     * Direção que desce o banner na lista da sua posição.
     */
    public const DOWN = 'down';

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
            'direction' => ['required', Rule::in([self::UP, self::DOWN])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'direction.required' => 'Informe a direção da movimentação.',
            'direction.in' => 'Direção de movimentação inválida.',
        ];
    }
}
