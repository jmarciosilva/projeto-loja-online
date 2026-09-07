<?php

namespace App\Http\Requests\Admin;

use App\Enums\BannerPosition;
use App\Services\BannerService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBannerRequest extends FormRequest
{
    /**
     * Durante a Fase 2 qualquer usuário autenticado administra os banners; o
     * middleware `auth` da rota é a única barreira. Papéis e permissões são
     * escopo da Fase 3 e não devem ser antecipados aqui.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Contrato de entrada do formulário administrativo.
     *
     * `sort_order` **não aparece**: a ordem é atribuída pelo `BannerService`,
     * que conhece as outras linhas da posição. Um campo aqui devolveria ao
     * administrador a tarefa de administrar inteiros — e, como o serviço monta
     * o próprio payload, um `sort_order` enviado à força seria ignorado de
     * qualquer forma.
     *
     * As regras antecipam ao formulário o que o serviço já garante; a fonte
     * autoritativa continua sendo o `BannerService`, inclusive fora do HTTP.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            // A mídia vem da biblioteca da F2.7 por identidade. `exists` é o
            // que impede gravar uma referência órfã; a FK do banco é a barreira
            // final, mas ela produziria erro de driver, não de formulário.
            'media_id' => ['required', 'integer', 'exists:media,id'],
            // Direto do enum, para que uma posição nova não exija lembrar de
            // atualizar uma lista paralela aqui.
            'position' => ['required', Rule::enum(BannerPosition::class)],
            'link_url' => ['nullable', 'string', 'max:2048', $this->linkIsSupported()],
            'alt_text' => ['required', 'string', 'max:255'],
            // A caixa de seleção viaja com um campo oculto, então o valor chega
            // sempre como "0" ou "1"; `nullable` cobre um POST sem o campo.
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Antecipa o contrato de link para dar erro no formulário.
     *
     * A regra não é reescrita aqui: quem responde é o próprio
     * `BannerService`, com a mesma normalização usada na gravação. Duplicar a
     * validação faria a interface e o domínio divergirem na primeira mudança.
     */
    private function linkIsSupported(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! app(BannerService::class)->isSupportedLink($value)) {
                $fail('Informe um caminho interno começando por / ou uma URL http/https com domínio válido.');
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
            'media_id' => 'imagem',
            'position' => 'posição',
            'link_url' => 'link',
            'alt_text' => 'texto alternativo',
            'is_active' => 'estado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'media_id.required' => 'Selecione uma imagem da biblioteca de mídia.',
            'media_id.exists' => 'A imagem selecionada não existe mais na biblioteca de mídia.',
            'position.required' => 'Selecione a posição do banner.',
        ];
    }
}
