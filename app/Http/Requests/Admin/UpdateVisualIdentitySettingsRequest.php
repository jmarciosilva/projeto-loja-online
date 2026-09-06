<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisualIdentitySettingsRequest extends FormRequest
{
    /**
     * Durante a Fase 2 qualquer usuário autenticado administra a identidade
     * visual; o middleware `auth` da rota é a única barreira. Papéis e
     * permissões são escopo da Fase 3 e não devem ser antecipados aqui.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Só duas referências de mídia entram, e ambas são opcionais.
     *
     * `nullable` é o contrato de "sem logo"/"sem favicon": a opção vazia do
     * formulário chega como `null` pelo middleware que converte strings vazias.
     *
     * `exists:media,id` é o que impede uma referência para mídia inexistente
     * ser gravada — sem isso, o storefront guardaria um id órfão e a proteção
     * de uso passaria a apontar para nada. `integer` rejeita array e texto.
     *
     * Nenhum outro campo é aceito: `validated()` devolve apenas estas duas
     * chaves, então um POST com configurações arbitrárias não chega ao
     * `SiteSettingService`.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'logo_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'favicon_media_id' => ['nullable', 'integer', 'exists:media,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'logo_media_id' => 'logo',
            'favicon_media_id' => 'favicon',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'logo_media_id.integer' => 'Selecione uma logo válida da biblioteca de mídia.',
            'logo_media_id.exists' => 'A logo selecionada não existe mais na biblioteca de mídia.',
            'favicon_media_id.integer' => 'Selecione um favicon válido da biblioteca de mídia.',
            'favicon_media_id.exists' => 'O favicon selecionado não existe mais na biblioteca de mídia.',
        ];
    }
}
