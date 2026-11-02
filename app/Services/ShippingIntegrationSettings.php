<?php

namespace App\Services;

use App\Enums\ShippingConnectionStatus;
use App\Enums\ShippingEnvironment;
use App\Enums\ShippingProviderCode;
use App\Models\ShippingProviderConfig;
use App\Models\ShippingSettings;
use Illuminate\Support\Arr;
use InvalidArgumentException;

/** Configuração administrativa sem qualquer comunicação com provedores. */
class ShippingIntegrationSettings
{
    /** @return array<string, mixed> */
    public function providerForAdmin(ShippingProviderCode $provider): array
    {
        $config = ShippingProviderConfig::query()->where('provider', $provider->value)->first();
        $credentials = $config?->credentials ?? [];
        $credentialFields = [];

        foreach ($provider->credentialLabels() as $key => $label) {
            $credentialFields[$key] = [
                'label' => $label,
                'configured' => filled($credentials[$key] ?? null),
            ];
        }

        $hasRequiredCredentials = collect($provider->requiredCredentialKeys())
            ->every(fn (string $key): bool => filled($credentials[$key] ?? null));
        $hasBaseConfiguration = $config !== null
            && $config->environment !== null
            && filled($config->origin_postal_code);

        // O Melhor Envio exige também autorização OAuth; o fluxo será entregue
        // numa etapa posterior, portanto Client ID/Secret não significam conexão.
        $oauthReady = $provider !== ShippingProviderCode::MelhorEnvio
            || filled($credentials['access_token'] ?? null);

        $configurationStatus = match (true) {
            $config === null => 'not_configured',
            ! $hasBaseConfiguration || ! $hasRequiredCredentials || ! $oauthReady => 'incomplete',
            default => 'configured',
        };

        return [
            'provider' => $provider,
            'environment' => $config?->environment?->value,
            'environmentOptions' => ShippingEnvironment::optionsFor($provider),
            'originPostalCode' => $config?->origin_postal_code,
            'enabledServiceCodes' => $config?->enabled_service_codes ?? [],
            'credentialFields' => $credentialFields,
            'configurationStatus' => $configurationStatus,
            'connectionStatus' => $config?->connection_status ?? ShippingConnectionStatus::NotTested,
            'lastTestedAt' => $config?->last_tested_at,
        ];
    }

    public function activeProvider(): ?ShippingProviderCode
    {
        $provider = ShippingSettings::query()
            ->where('key', ShippingSettings::SINGLETON_KEY)
            ->value('active_provider');

        if ($provider instanceof ShippingProviderCode) {
            return $provider;
        }

        return is_string($provider) ? ShippingProviderCode::tryFrom($provider) : null;
    }

    /** @param array<string, mixed> $validated */
    public function updateProvider(ShippingProviderCode $provider, array $validated): ShippingProviderConfig
    {
        $config = ShippingProviderConfig::query()->firstOrNew(['provider' => $provider->value]);
        $credentials = $config->credentials ?? [];

        foreach ($provider->credentialLabels() as $key => $_label) {
            $value = Arr::get($validated, "credentials.{$key}");

            // Campos em branco preservam segredos. A remoção é uma operação
            // separada, explícita e protegida pela Policy.
            if (is_string($value) && trim($value) !== '') {
                $credentials[$key] = trim($value);
            }
        }

        $environment = ShippingEnvironment::tryFrom($validated['environment']);
        if ($environment === null || ! array_key_exists($environment->value, ShippingEnvironment::optionsFor($provider))) {
            throw new InvalidArgumentException('O ambiente informado não é compatível com o provedor.');
        }

        $serviceCodes = array_values(array_unique(array_map(
            static fn (string $code): string => trim($code),
            $validated['enabled_service_codes'] ?? [],
        )));

        $config->fill([
            'environment' => $environment,
            'origin_postal_code' => preg_replace('/\D/', '', $validated['origin_postal_code']),
            'enabled_service_codes' => $serviceCodes,
            'credentials' => $credentials === [] ? null : $credentials,
            // Qualquer alteração de configuração invalida um resultado de
            // verificação anterior; nenhuma chamada real existe nesta etapa.
            'connection_status' => ShippingConnectionStatus::NotTested,
            'last_tested_at' => null,
            'last_error_code' => null,
        ])->save();

        return $config;
    }

    public function selectActiveProvider(?ShippingProviderCode $provider): ShippingSettings
    {
        $settings = ShippingSettings::query()->firstOrNew(['key' => ShippingSettings::SINGLETON_KEY]);
        $settings->active_provider = $provider;
        $settings->save();

        return $settings;
    }

    public function removeCredentials(ShippingProviderCode $provider): void
    {
        $config = ShippingProviderConfig::query()->where('provider', $provider->value)->first();

        if ($config === null) {
            return;
        }

        $credentials = $config->credentials ?? [];
        foreach (array_keys($provider->credentialLabels()) as $key) {
            unset($credentials[$key]);
        }

        // Tokens OAuth futuros também pertencem a esta credencial e devem ser
        // removidos juntos quando o vínculo do provedor for explicitamente limpo.
        if ($provider === ShippingProviderCode::MelhorEnvio) {
            unset($credentials['access_token'], $credentials['refresh_token'], $credentials['expires_at']);
        }

        $config->fill([
            'credentials' => $credentials === [] ? null : $credentials,
            'connection_status' => ShippingConnectionStatus::NotTested,
            'last_tested_at' => null,
            'last_error_code' => null,
        ])->save();
    }
}
