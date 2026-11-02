<?php

namespace App\Enums;

use App\Models\ProductShippingProfile;

enum ShippingProfileReadiness: string
{
    case ProfilePending = 'profile_pending';
    case NotRequired = 'not_required';
    case ProductMeasurementsPending = 'product_measurements_pending';
    case PackageMeasurementsPending = 'package_measurements_pending';
    case Ready = 'ready';

    public static function for(?ProductShippingProfile $profile): self
    {
        if ($profile === null) {
            return self::ProfilePending;
        }

        if (! $profile->requires_shipping) {
            return self::NotRequired;
        }

        if (! $profile->hasProductMeasurements()) {
            return self::ProductMeasurementsPending;
        }

        if (! $profile->hasPackageMeasurements()) {
            return self::PackageMeasurementsPending;
        }

        return self::Ready;
    }

    public function label(): string
    {
        return match ($this) {
            self::ProfilePending => 'Dados de envio pendentes: defina se o produto exige envio.',
            self::NotRequired => 'Este produto não exige envio físico.',
            self::ProductMeasurementsPending => 'Dados de envio pendentes: informe peso e dimensões do produto.',
            self::PackageMeasurementsPending => 'Dados do pacote pendentes: informe peso e dimensões da embalagem.',
            self::Ready => 'Pronto para preparar cotação de uma unidade. Nenhuma conexão foi consultada.',
        };
    }

    public function toneClass(): string
    {
        return match ($this) {
            self::Ready => 'text-green-800',
            self::NotRequired => 'text-gray-600',
            self::ProfilePending, self::ProductMeasurementsPending, self::PackageMeasurementsPending => 'text-amber-800',
        };
    }
}
