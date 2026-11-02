<?php

namespace App\Enums;

enum ShippingEnvironment: string
{
    case Sandbox = 'sandbox';
    case Homologation = 'homologation';
    case Production = 'production';

    /** @return array<string, string> */
    public static function optionsFor(ShippingProviderCode $provider): array
    {
        return match ($provider) {
            ShippingProviderCode::Frenet => [
                self::Homologation->value => 'Homologação',
                self::Production->value => 'Produção',
            ],
            ShippingProviderCode::MelhorEnvio => [
                self::Sandbox->value => 'Sandbox',
                self::Production->value => 'Produção',
            ],
        };
    }
}
