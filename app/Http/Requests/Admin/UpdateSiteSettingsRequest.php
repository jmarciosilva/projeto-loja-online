<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSettingsRequest extends FormRequest
{
    /**
     * Durante a Fase 2 qualquer usuário autenticado administra as configurações;
     * o middleware `auth` da rota é a única barreira. Papéis e permissões são
     * escopo da Fase 3 e não devem ser antecipados aqui.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * O endereço é uma configuração textual institucional — não é decomposto em
     * CEP, rua ou cidade. O telefone aceita formato livre: uma máscara rígida
     * brasileira rejeitaria números internacionais sem ganho nesta fase.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:150',
            'support_email' => 'nullable|email|max:254',
            'phone' => 'nullable|string|max:30',
            'address' => 'nullable|string|max:500',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome da loja',
            'support_email' => 'email de suporte',
            'phone' => 'telefone',
            'address' => 'endereço',
        ];
    }
}
