<?php

namespace App\Http\Requests\Admin;

use App\Enums\MenuItemType;
use App\Services\MenuService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entrada do formulário de item de menu.
 *
 * Três campos do domínio **não aparecem** aqui, e a ausência é o contrato:
 *
 * ```text
 * menu_id     → vem do contexto da rota, não do formulário
 * sort_order  → é atribuído pelo MenuService, que conhece os irmãos
 * ```
 *
 * O destino é validado de forma **condicional e antecipada**: `page` exige
 * `page_id` e recusa `url`; `url` exige `url` e recusa `page_id`. É a mesma
 * exclusividade que o `MenuService` garante ao gravar — aqui ela existe para
 * que o erro apareça no campo certo, com o input preservado, em vez de voltar
 * como exceção de domínio.
 *
 * A política de URL não é reescrita: quem responde é o próprio serviço.
 * As invariantes estruturais — pertencimento do pai ao menu e ausência de
 * ciclos — continuam **só** no `MenuService`; o formulário apenas oferece
 * opções válidas.
 */
class StoreMenuItemRequest extends FormRequest
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
            'label' => ['required', 'string', 'max:120'],
            // Direto do enum, para que um destino novo não exija lembrar de
            // atualizar uma lista paralela aqui.
            'type' => ['required', Rule::enum(MenuItemType::class)],
            // Identidade da página, nunca o slug: o slug é endereço público e
            // mutável. `deleted_at` nulo porque `Page` usa SoftDeletes — a
            // linha física continua na tabela e a FK aceitaria, mas criar um
            // vínculo novo com algo que está na lixeira é engano, não intenção.
            'page_id' => [
                'nullable',
                'required_if:type,'.MenuItemType::Page->value,
                'prohibited_if:type,'.MenuItemType::Url->value,
                'integer',
                Rule::exists('pages', 'id')->whereNull('deleted_at'),
            ],
            'url' => [
                'nullable',
                'required_if:type,'.MenuItemType::Url->value,
                'prohibited_if:type,'.MenuItemType::Page->value,
                'string',
                'max:2048',
                $this->urlIsSupported(),
            ],
            // Vazio é a raiz. O middleware padrão do Laravel converte string
            // vazia em null, então o `<option value="">` do formulário chega
            // como ausência de pai, e não como zero.
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Antecipa o contrato de URL para dar erro no formulário.
     *
     * A regra não é reescrita: quem responde é o `MenuService`, com a mesma
     * normalização usada na gravação — é o arranjo já adotado por
     * `StoreBannerRequest::linkIsSupported()`.
     */
    private function urlIsSupported(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! app(MenuService::class)->isSupportedUrl($value)) {
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
            'label' => 'rótulo',
            'type' => 'destino',
            'page_id' => 'página',
            'url' => 'URL',
            'parent_id' => 'item pai',
            'is_active' => 'estado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'label.required' => 'Informe o rótulo do item.',
            'type.required' => 'Escolha o destino do item.',
            'page_id.required_if' => 'Selecione a página de destino.',
            'page_id.prohibited_if' => 'Um item de URL não aponta para uma página.',
            'page_id.exists' => 'A página selecionada não está mais disponível.',
            'url.required_if' => 'Informe a URL de destino.',
            'url.prohibited_if' => 'Um item de página não aceita URL própria.',
            'parent_id.exists' => 'O item pai selecionado não existe mais.',
        ];
    }
}
