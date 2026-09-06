<?php

namespace App\Http\Requests\Admin;

use App\Models\Media;
use App\Services\VisualIdentityService;
use Closure;
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
            'favicon_media_id' => ['nullable', 'integer', 'exists:media,id', $this->faviconIsPng()],
        ];
    }

    /**
     * Antecipa a regra de formato do favicon para dar erro no formulário.
     *
     * O contrato — favicon vem da biblioteca em PNG, porque `.ico` e `.svg`
     * estão fora do escopo da F2.7 — é do domínio, e a invariante continua
     * garantida pelo `VisualIdentityService`, inclusive fora do HTTP. Esta
     * validação é conveniência de interface, não a fonte autoritativa.
     *
     * A decisão sai do `mime_type` gravado pela F2.7, nunca da extensão ou do
     * nome original do arquivo.
     */
    private function faviconIsPng(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            $media = Media::query()->find($value);

            // Mídia inexistente já é reportada pela regra `exists`; repetir o
            // erro aqui poluiria o formulário com duas mensagens para a mesma
            // causa.
            if ($media === null) {
                return;
            }

            if (! app(VisualIdentityService::class)->isSupportedFavicon($media)) {
                $fail('O favicon deve ser uma imagem PNG da biblioteca de mídia.');
            }
        };
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
