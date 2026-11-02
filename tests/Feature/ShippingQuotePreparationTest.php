<?php

namespace Tests\Feature;

use App\Enums\ShippingEnvironment;
use App\Enums\ShippingProfileReadiness;
use App\Enums\ShippingProviderCode;
use App\Enums\ShippingQuotePreparationStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ShippingProviderConfig;
use App\Services\Shipping\ShippingQuotePreparationService;
use App\Services\ShippingIntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\TestCase;

class ShippingQuotePreparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_one_complete_physical_product_prepares_normalized_request_using_server_data(): void
    {
        $product = $this->physicalProduct();
        $this->configureProvider();

        $result = app(ShippingQuotePreparationService::class)->prepare([
            ['product_id' => $product->id, 'quantity' => 1],
        ], '20040-030');

        $this->assertSame(ShippingQuotePreparationStatus::Ready, $result->status);
        $request = $result->request;
        $this->assertSame('01310100', $request->originPostalCode);
        $this->assertSame('20040030', $request->destinationPostalCode);
        $this->assertSame(ShippingProviderCode::Frenet, $request->provider);
        $this->assertSame(350, $request->packages[0]->weightGrams);
        $this->assertSame(30, $request->packages[0]->heightMillimeters);
        $this->assertSame(160, $request->packages[0]->widthMillimeters);
        $this->assertSame(230, $request->packages[0]->lengthMillimeters);
        $this->assertSame(3500, $request->declaredValueCents);
    }

    public function test_missing_profile_or_incomplete_product_or_package_data_is_unavailable(): void
    {
        $missing = $this->publicProduct('Sem perfil');
        $incomplete = $this->publicProduct('Sem peso');
        $incomplete->shippingProfile()->create(['requires_shipping' => true]);
        $noPackage = $this->publicProduct('Sem pacote');
        $noPackage->shippingProfile()->create([
            'requires_shipping' => true, 'weight_kg' => '0.4', 'height_cm' => '2', 'width_cm' => '14', 'length_cm' => '21',
        ]);

        $service = app(ShippingQuotePreparationService::class);
        $this->assertSame('shipping_profile_pending', $service->prepare([['product_id' => $missing->id, 'quantity' => 1]], '12345678')->reasonCode);
        $this->assertSame('product_measurements_incomplete', $service->prepare([['product_id' => $incomplete->id, 'quantity' => 1]], '12345678')->reasonCode);
        $this->assertSame('package_measurements_incomplete', $service->prepare([['product_id' => $noPackage->id, 'quantity' => 1]], '12345678')->reasonCode);
    }

    public function test_products_explicitly_marked_as_not_shipped_are_excluded_and_all_no_shipping_returns_not_required(): void
    {
        $digital = $this->publicProduct('Arquivo digital');
        $digital->shippingProfile()->create(['requires_shipping' => false]);
        $service = app(ShippingQuotePreparationService::class);

        $this->assertSame(ShippingQuotePreparationStatus::NotRequired,
            $service->prepare([['product_id' => $digital->id, 'quantity' => 1]], '12345678')->status);

        $physical = $this->physicalProduct();
        $this->configureProvider();
        $mixed = $service->prepare([
            ['product_id' => $physical->id, 'quantity' => 1],
            ['product_id' => $digital->id, 'quantity' => 2],
        ], '12345678');
        $this->assertSame(ShippingQuotePreparationStatus::Ready, $mixed->status);
        $this->assertCount(1, $mixed->request->packages);
    }

    public function test_current_package_policy_reports_multiple_items_as_unavailable(): void
    {
        $first = $this->physicalProduct('Livro A');
        $second = $this->physicalProduct('Livro B');
        $this->configureProvider();
        $service = app(ShippingQuotePreparationService::class);

        $this->assertSame('multiple_physical_products_not_supported', $service->prepare([
            ['product_id' => $first->id, 'quantity' => 1], ['product_id' => $second->id, 'quantity' => 1],
        ], '12345678')->reasonCode);
        $this->assertSame('multiple_units_not_supported', $service->prepare([
            ['product_id' => $first->id, 'quantity' => 2],
        ], '12345678')->reasonCode);
    }

    public function test_invalid_quantities_empty_items_and_destination_are_rejected(): void
    {
        $product = $this->physicalProduct();
        $service = app(ShippingQuotePreparationService::class);
        foreach ([0, -1, '1.5', '0'] as $quantity) {
            try {
                $service->prepare([['product_id' => $product->id, 'quantity' => $quantity]], '12345678');
                $this->fail('Quantidade inválida foi aceita.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $this->expectException(InvalidArgumentException::class);
        $service->prepare([], '12345678');
    }

    public function test_provider_disabled_or_origin_missing_never_returns_a_quote_request(): void
    {
        $product = $this->physicalProduct();
        $service = app(ShippingQuotePreparationService::class);
        $this->assertSame('shipping_provider_disabled', $service->prepare([['product_id' => $product->id, 'quantity' => 1]], '12345678')->reasonCode);

        $this->configureProvider(false);
        $this->assertSame('origin_postal_code_missing', $service->prepare([['product_id' => $product->id, 'quantity' => 1]], '12345678')->reasonCode);
    }

    private function physicalProduct(string $name = 'Livro pronto'): Product
    {
        $product = $this->publicProduct($name, '35.00');
        $profile = $product->shippingProfile()->create([
            'requires_shipping' => true,
            'weight_kg' => '0.350', 'height_cm' => '2.50', 'width_cm' => '14.00', 'length_cm' => '21.00',
            'package_weight_kg' => '0.350', 'package_height_cm' => '3.00',
            'package_width_cm' => '16.00', 'package_length_cm' => '23.00',
        ]);
        $this->assertSame(ShippingProfileReadiness::Ready, ShippingProfileReadiness::for($profile));

        return $product;
    }

    private function publicProduct(string $name, string $price = '35.00'): Product
    {
        $category = Category::factory()->create(['is_active' => true]);

        return Product::factory()->create([
            'category_id' => $category->id, 'name' => $name, 'is_active' => true, 'price' => $price,
        ]);
    }

    private function configureProvider(bool $includeOrigin = true): void
    {
        ShippingProviderConfig::query()->create([
            'provider' => ShippingProviderCode::Frenet,
            'environment' => ShippingEnvironment::Homologation,
            'origin_postal_code' => $includeOrigin ? '01310100' : '',
            'enabled_service_codes' => [],
            'credentials' => null,
        ]);
        app(ShippingIntegrationSettings::class)->selectActiveProvider(ShippingProviderCode::Frenet);
    }
}
