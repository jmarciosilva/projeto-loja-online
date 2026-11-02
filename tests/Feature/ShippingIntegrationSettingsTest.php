<?php

namespace Tests\Feature;

use App\Enums\ShippingConnectionStatus;
use App\Enums\ShippingProviderCode;
use App\Models\ShippingProviderConfig;
use App\Models\ShippingSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingIntegrationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipping_settings_are_restricted_to_users_with_the_specific_permission(): void
    {
        $this->get(route('admin.integrations.shipping.edit'))->assertRedirect(route('login'));

        $user = User::factory()->create([
            'can_manage_catalog' => true,
            'can_manage_shipping_integrations' => false,
        ]);
        $this->actingAs($user)
            ->get(route('admin.integrations.shipping.edit'))
            ->assertForbidden();
        $this->get(route('admin'))->assertDontSee(route('admin.integrations.shipping.edit'), false);

        $this->actingAs($user)
            ->put(route('admin.integrations.shipping.active-provider.update'), ['active_provider' => 'frenet'])
            ->assertForbidden();

        $user->forceFill(['can_manage_shipping_integrations' => true])->save();
        $this->actingAs($user)
            ->get(route('admin.integrations.shipping.edit'))
            ->assertOk()
            ->assertSee('Integrações')
            ->assertSee('Frete')
            ->assertSee('Desativado')
            ->assertSee('Frenet')
            ->assertSee('Melhor Envio')
            ->assertSee('Conexão não testada')
            ->assertSee(route('admin.integrations.shipping.edit'), false);
    }

    public function test_provider_settings_are_saved_encrypted_and_secrets_are_never_rendered(): void
    {
        $this->actingAs($this->shippingAdmin());

        $response = $this->put(route('admin.integrations.shipping.provider.update', 'frenet'), [
            'environment' => 'homologation',
            'origin_postal_code' => '01310-100',
            'enabled_service_codes' => "04014\n04510",
            'credentials' => [
                'client_token' => 'token-cliente-secreto',
                'partner_token' => 'partner-secreto',
            ],
        ]);

        $response->assertRedirect(route('admin.integrations.shipping.edit'));
        $config = ShippingProviderConfig::query()->where('provider', 'frenet')->sole();
        $this->assertSame('01310100', $config->origin_postal_code);
        $this->assertSame(['04014', '04510'], $config->enabled_service_codes);
        $this->assertSame('token-cliente-secreto', $config->credentials['client_token']);
        $this->assertSame(ShippingConnectionStatus::NotTested, $config->connection_status);

        $storedCredentials = $config->getRawOriginal('credentials');
        $this->assertNotSame('token-cliente-secreto', $storedCredentials);
        $this->assertNotSame('partner-secreto', $storedCredentials);
        $this->assertStringNotContainsString('token-cliente-secreto', $storedCredentials);
        $this->assertStringNotContainsString('partner-secreto', $storedCredentials);

        $this->get(route('admin.integrations.shipping.edit'))
            ->assertOk()
            ->assertDontSee('token-cliente-secreto')
            ->assertDontSee('partner-secreto')
            ->assertSee('Já configurado — deixe vazio para preservar');
    }

    public function test_blank_credential_fields_preserve_existing_values_and_removal_is_explicit(): void
    {
        $this->actingAs($this->shippingAdmin());
        $this->salvarFrenet();

        $this->put(route('admin.integrations.shipping.provider.update', 'frenet'), [
            'environment' => 'production',
            'origin_postal_code' => '20040-020',
            'enabled_service_codes' => '',
            'credentials' => ['client_token' => '', 'partner_token' => '   '],
        ])->assertRedirect();

        $config = ShippingProviderConfig::query()->where('provider', 'frenet')->sole();
        $this->assertSame('token-cliente-secreto', $config->credentials['client_token']);
        $this->assertSame('partner-secreto', $config->credentials['partner_token']);

        $this->delete(route('admin.integrations.shipping.credentials.destroy', 'frenet'))
            ->assertRedirect(route('admin.integrations.shipping.edit'));

        $this->assertNull($config->fresh()->credentials);
        $this->assertSame('20040020', $config->fresh()->origin_postal_code);
    }

    public function test_provider_configurations_are_independent_and_active_provider_can_be_disabled(): void
    {
        $this->actingAs($this->shippingAdmin());
        $this->salvarFrenet();

        $this->put(route('admin.integrations.shipping.provider.update', 'melhor_envio'), [
            'environment' => 'sandbox',
            'origin_postal_code' => '30110-012',
            'enabled_service_codes' => '1',
            'credentials' => ['client_id' => 'id-me', 'client_secret' => 'secret-me'],
        ])->assertRedirect();

        $this->put(route('admin.integrations.shipping.active-provider.update'), ['active_provider' => 'melhor_envio'])
            ->assertRedirect();
        $this->assertSame(ShippingProviderCode::MelhorEnvio, ShippingSettings::query()->sole()->active_provider);
        $this->assertSame('token-cliente-secreto', ShippingProviderConfig::query()->where('provider', 'frenet')->sole()->credentials['client_token']);
        $this->assertSame('id-me', ShippingProviderConfig::query()->where('provider', 'melhor_envio')->sole()->credentials['client_id']);

        $this->put(route('admin.integrations.shipping.active-provider.update'), ['active_provider' => null])
            ->assertRedirect();
        $this->assertNull(ShippingSettings::query()->sole()->active_provider);
    }

    public function test_unknown_providers_and_incompatible_environments_are_rejected(): void
    {
        $this->actingAs($this->shippingAdmin());

        $this->put(route('admin.integrations.shipping.active-provider.update'), ['active_provider' => 'correios'])
            ->assertSessionHasErrors('active_provider');
        $this->put(route('admin.integrations.shipping.provider.update', 'frenet'), [
            'environment' => 'sandbox',
            'origin_postal_code' => '01310-100',
            'enabled_service_codes' => '',
            'credentials' => [],
        ])->assertSessionHasErrors('environment');

        $this->get('/admin/integracoes/frete/provedor-desconhecido')->assertNotFound();
    }

    public function test_provider_secrets_are_not_exposed_by_model_serialization_and_connection_stays_untested(): void
    {
        $this->actingAs($this->shippingAdmin());
        $this->salvarFrenet();

        $config = ShippingProviderConfig::query()->where('provider', 'frenet')->sole();
        $this->assertArrayNotHasKey('credentials', $config->toArray());
        $this->assertSame(ShippingConnectionStatus::NotTested, $config->connection_status);
        $this->assertNull($config->last_tested_at);
    }

    private function shippingAdmin(): User
    {
        return User::factory()->create(['can_manage_shipping_integrations' => true]);
    }

    private function salvarFrenet(): void
    {
        $this->put(route('admin.integrations.shipping.provider.update', 'frenet'), [
            'environment' => 'homologation',
            'origin_postal_code' => '01310-100',
            'enabled_service_codes' => '',
            'credentials' => [
                'client_token' => 'token-cliente-secreto',
                'partner_token' => 'partner-secreto',
            ],
        ])->assertRedirect();
    }
}
