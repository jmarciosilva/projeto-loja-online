<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateThemeSettingsRequest extends FormRequest
{
    /**
     * Contrato de cor aceito nesta fase: hexadecimal de seis dígitos.
     *
     * A forma abreviada de três dígitos, `rgb()`, `hsl()`, nomes de cor e
     * qualquer outra sintaxe CSS ficam de fora — os valores são interpolados
     * dentro de um bloco `<style>`, e um contrato estreito é o que impede
     * conteúdo arbitrário de chegar lá.
     */
    private const HEX_COLOR = 'regex:/^#[0-9A-Fa-f]{6}$/';

    /**
     * Durante a Fase 2 qualquer usuário autenticado administra o tema; o
     * middleware `auth` da rota é a única barreira. Papéis e permissões são
     * escopo da Fase 3.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'primary_color' => ['required', 'string', self::HEX_COLOR],
            'secondary_color' => ['required', 'string', self::HEX_COLOR],
            'accent_color' => ['required', 'string', self::HEX_COLOR],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => 'A cor primária deve estar no formato #RRGGBB.',
            'secondary_color.regex' => 'A cor secundária deve estar no formato #RRGGBB.',
            'accent_color.regex' => 'A cor de destaque deve estar no formato #RRGGBB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'primary_color' => 'cor primária',
            'secondary_color' => 'cor secundária',
            'accent_color' => 'cor de destaque',
        ];
    }
}
