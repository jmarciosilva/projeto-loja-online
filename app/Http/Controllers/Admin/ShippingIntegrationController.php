<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShippingProviderCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateActiveShippingProviderRequest;
use App\Http\Requests\Admin\UpdateShippingProviderConfigRequest;
use App\Models\ShippingSettings;
use App\Services\ShippingIntegrationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ShippingIntegrationController extends Controller
{
    public function edit(ShippingIntegrationSettings $settings): View
    {
        $this->authorize('manage', ShippingSettings::class);

        $providers = [];
        foreach (ShippingProviderCode::cases() as $provider) {
            $providers[$provider->value] = $settings->providerForAdmin($provider);
        }

        return view('admin.integrations.shipping', [
            'providers' => $providers,
            'activeProvider' => $settings->activeProvider(),
        ]);
    }

    public function updateProvider(
        UpdateShippingProviderConfigRequest $request,
        string $provider,
        ShippingIntegrationSettings $settings,
    ): RedirectResponse {
        $this->authorize('manage', ShippingSettings::class);
        $providerCode = ShippingProviderCode::tryFrom($provider);
        abort_if($providerCode === null, 404);

        $settings->updateProvider($providerCode, $request->validated());

        return redirect()
            ->route('admin.integrations.shipping.edit')
            ->with('status', "Configurações de {$providerCode->label()} salvas. Nenhuma conexão foi testada.");
    }

    public function updateActiveProvider(
        UpdateActiveShippingProviderRequest $request,
        ShippingIntegrationSettings $settings,
    ): RedirectResponse {
        $this->authorize('manage', ShippingSettings::class);
        $provider = $request->validated('active_provider');
        $provider = $provider === null ? null : ShippingProviderCode::from($provider);
        $settings->selectActiveProvider($provider);

        return redirect()
            ->route('admin.integrations.shipping.edit')
            ->with('status', $provider === null
                ? 'Integrações de frete desativadas.'
                : $provider->label().' selecionado para futuras cotações.');
    }

    public function removeCredentials(string $provider, ShippingIntegrationSettings $settings): RedirectResponse
    {
        $this->authorize('manage', ShippingSettings::class);

        $providerCode = ShippingProviderCode::tryFrom($provider);
        abort_if($providerCode === null, 404);

        $settings->removeCredentials($providerCode);

        return redirect()
            ->route('admin.integrations.shipping.edit')
            ->with('status', "Credenciais de {$providerCode->label()} removidas.");
    }
}
