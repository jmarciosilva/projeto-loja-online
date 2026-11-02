<?php

namespace App\Enums;

enum ShippingConnectionStatus: string
{
    case NotTested = 'not_tested';
    case Validated = 'validated';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::NotTested => 'Conexão não testada',
            self::Validated => 'Conexão validada',
            self::Error => 'Último teste apresentou erro',
        };
    }
}
