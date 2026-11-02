<?php

namespace App\Http\Requests\Admin;

use App\Enums\ShippingProviderCode;
use App\Models\ShippingSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateActiveShippingProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', ShippingSettings::class) ?? false;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'active_provider' => ['nullable', 'string', Rule::enum(ShippingProviderCode::class)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'active_provider.enum' => 'Selecione um provedor conhecido ou desative as integrações de frete.',
        ];
    }
}
