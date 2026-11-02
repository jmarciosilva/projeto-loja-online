<?php

namespace App\Services\Shipping;

use App\Contracts\Shipping\ShippingPackagePreparer;
use App\DTO\Shipping\ShippingPackage;
use App\DTO\Shipping\ShippingPackageCandidate;
use App\DTO\Shipping\ShippingQuotePreparationResult;
use App\DTO\Shipping\ShippingQuoteRequest;
use App\Models\Product;
use App\Models\ShippingProviderConfig;
use App\Services\ShippingIntegrationSettings;
use InvalidArgumentException;

class ShippingQuotePreparationService
{
    public function __construct(
        private readonly ShippingIntegrationSettings $integrationSettings,
        private readonly ShippingPackagePreparer $packagePreparer,
    ) {}

    /**
     * Resolve produtos e preços exclusivamente no servidor.
     *
     * @param  list<array{product_id: int|string, quantity: int|string}>  $items
     */
    public function prepare(array $items, string $destinationPostalCode): ShippingQuotePreparationResult
    {
        if ($items === []) {
            throw new InvalidArgumentException('Informe ao menos um produto para preparar a cotação.');
        }

        $destination = $this->normalizePostalCode($destinationPostalCode);
        $quantities = $this->normalizeItems($items);
        $products = Product::query()->publiclyVisible()->with('shippingProfile')
            ->whereIn('id', array_keys($quantities))->get()->keyBy('id');

        if ($products->count() !== count($quantities)) {
            return ShippingQuotePreparationResult::unavailable('product_unavailable');
        }

        $candidates = [];
        foreach ($quantities as $productId => $quantity) {
            /** @var Product $product */
            $product = $products->get($productId);
            $profile = $product->shippingProfile;

            // Sem perfil não é possível inferir que o produto é digital.
            if ($profile === null) {
                return ShippingQuotePreparationResult::unavailable('shipping_profile_pending');
            }

            if (! $profile->requires_shipping) {
                continue;
            }

            if (! $profile->hasProductMeasurements()) {
                return ShippingQuotePreparationResult::unavailable('product_measurements_incomplete');
            }

            if (! $profile->hasPackageMeasurements()) {
                return ShippingQuotePreparationResult::unavailable('package_measurements_incomplete');
            }

            // Valida a representação normalizada das medidas do produto,
            // mas não as usa como dimensões do pacote.
            ShippingMeasurementConverter::kilogramsToGrams($profile->weight_kg);
            ShippingMeasurementConverter::centimetersToMillimeters($profile->height_cm);
            ShippingMeasurementConverter::centimetersToMillimeters($profile->width_cm);
            ShippingMeasurementConverter::centimetersToMillimeters($profile->length_cm);

            $package = new ShippingPackage(
                ShippingMeasurementConverter::kilogramsToGrams($profile->package_weight_kg),
                ShippingMeasurementConverter::centimetersToMillimeters($profile->package_height_cm),
                ShippingMeasurementConverter::centimetersToMillimeters($profile->package_width_cm),
                ShippingMeasurementConverter::centimetersToMillimeters($profile->package_length_cm),
            );

            $candidates[] = new ShippingPackageCandidate(
                (int) $product->id,
                $quantity,
                ShippingMeasurementConverter::decimalToCents($product->effectivePrice()),
                $package,
            );
        }

        if ($candidates === []) {
            return ShippingQuotePreparationResult::notRequired();
        }

        $packagePreparation = $this->packagePreparer->prepare($candidates);
        if (! $packagePreparation->available) {
            return ShippingQuotePreparationResult::unavailable($packagePreparation->reasonCode ?? 'package_preparation_unavailable');
        }

        $provider = $this->integrationSettings->activeProvider();
        if ($provider === null) {
            return ShippingQuotePreparationResult::unavailable('shipping_provider_disabled');
        }

        $providerConfig = ShippingProviderConfig::query()->where('provider', $provider->value)->first();
        if ($providerConfig === null || ! filled($providerConfig->origin_postal_code)) {
            return ShippingQuotePreparationResult::unavailable('origin_postal_code_missing');
        }

        return ShippingQuotePreparationResult::ready(new ShippingQuoteRequest(
            preg_replace('/\D/', '', $providerConfig->origin_postal_code),
            $destination,
            $packagePreparation->packages,
            $packagePreparation->declaredValueCents,
            $provider,
        ));
    }

    /** @param list<array{product_id: int|string, quantity: int|string}> $items @return array<int, int> */
    private function normalizeItems(array $items): array
    {
        $quantities = [];
        foreach ($items as $item) {
            if (! is_array($item)
                || ! array_key_exists('product_id', $item)
                || ! array_key_exists('quantity', $item)) {
                throw new InvalidArgumentException('Cada produto precisa de identificador e quantidade.');
            }

            $productId = $this->positiveInteger($item['product_id'], 'produto');
            $quantity = $this->positiveInteger($item['quantity'], 'quantidade');
            if ($quantity > 1000) {
                throw new InvalidArgumentException('A quantidade máxima por produto é 1.000 unidades.');
            }
            if (array_key_exists($productId, $quantities)) {
                throw new InvalidArgumentException('Não repita o mesmo produto em linhas diferentes.');
            }

            $quantities[$productId] = $quantity;
        }

        return $quantities;
    }

    private function positiveInteger(mixed $value, string $field): int
    {
        if ((! is_int($value) && ! is_string($value))
            || preg_match('/^[1-9]\d*$/', (string) $value) !== 1) {
            throw new InvalidArgumentException("O campo {$field} precisa ser um inteiro positivo.");
        }

        return (int) $value;
    }

    private function normalizePostalCode(string $value): string
    {
        $digits = preg_replace('/\D/', '', trim($value));
        if (! is_string($digits) || preg_match('/^\d{8}$/', $digits) !== 1) {
            throw new InvalidArgumentException('Informe um CEP de destino válido com oito dígitos.');
        }

        return $digits;
    }
}
