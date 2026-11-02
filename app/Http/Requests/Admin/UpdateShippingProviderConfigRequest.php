<?php

namespace App\Http\Requests\Admin;

use App\Enums\ShippingEnvironment;
use App\Enums\ShippingProviderCode;
use App\Models\ShippingSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateShippingProviderConfigRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $codes = $this->input('enabled_service_codes', []);

        if (is_string($codes)) {
            $codes = preg_split('/\R/', $codes) ?: [];
        }

        $this->merge(['enabled_service_codes' => $codes]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manage', ShippingSettings::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $provider = ShippingProviderCode::tryFrom((string) $this->route('provider'));
        $allowedEnvironments = $provider === null
            ? []
            : array_keys(ShippingEnvironment::optionsFor($provider));
        $credentialKeys = $provider?->requiredCredentialKeys() ?? [];
        $rules = [
            'environment' => ['required', 'string', Rule::in($allowedEnvironments)],
            'origin_postal_code' => ['required', 'string', 'regex:/^\d{5}-?\d{3}$/'],
            'enabled_service_codes' => ['nullable', 'array', 'max:30'],
            'enabled_service_codes.*' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/', 'distinct'],
            'credentials' => ['nullable', 'array:'.implode(',', $credentialKeys)],
        ];

        foreach ($credentialKeys as $key) {
            $rules["credentials.{$key}"] = ['nullable', 'string', 'max:4096'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'environment.in' => 'Selecione um ambiente compatível com o provedor.',
            'origin_postal_code.regex' => 'Informe um CEP de origem válido com oito dígitos.',
            'enabled_service_codes.*.regex' => 'Use somente letras, números, ponto, sublinhado ou hífen no código do serviço.',
            'credentials.array' => 'Os campos de credenciais não correspondem a este provedor.',
        ];
    }
}
