<?php

namespace App\Http\Requests\Admin;

use App\Enums\PageStatus;
use App\Services\PageService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePageRequest extends FormRequest
{
    /**
     * Durante a Fase 2 qualquer usuário autenticado administra as páginas; o
     * middleware `auth` da rota é a única barreira. Papéis e permissões são
     * escopo da Fase 3 e não devem ser antecipados aqui.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * O slug é opcional na criação: em branco, o `PageService` o deriva do
     * título. Quando preenchido, vale o formato canônico — a interface não
     * conserta `Quem Somos` para `quem-somos` em silêncio, porque o endereço
     * foi escolhido deliberadamente e trocá-lo publicaria outra URL.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $this->slugAvailability(),
            ],
            'status' => ['required', Rule::enum(PageStatus::class)],
            'content' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ];
    }

    /**
     * Antecipa a checagem de disponibilidade só para dar erro no formulário.
     *
     * A regra é consultada no `PageService`, e não reescrita como um `unique`
     * de banco: `unique` ignoraria as páginas excluídas logicamente, que
     * continuam reservando seus endereços. O serviço permanece a fonte
     * autoritativa — esta validação é conveniência de UX, não a invariante.
     */
    protected function slugAvailability(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            if (! app(PageService::class)->slugIsAvailable($value, $this->ignoredPageId())) {
                $fail('Este endereço já está em uso por outra página.');
            }
        };
    }

    /**
     * Na criação não há página a ignorar; a atualização sobrescreve isto.
     */
    protected function ignoredPageId(): ?int
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'título',
            'slug' => 'endereço',
            'status' => 'status',
            'content' => 'conteúdo',
            'meta_title' => 'meta title',
            'meta_description' => 'meta description',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'O endereço aceita apenas letras minúsculas, números e hífens — por exemplo, quem-somos.',
        ];
    }
}
