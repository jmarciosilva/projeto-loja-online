<?php

namespace App\Enums;

enum ShippingProviderCode: string
{
    case Frenet = 'frenet';
    case MelhorEnvio = 'melhor_envio';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Frenet => 'Frenet',
            self::MelhorEnvio => 'Melhor Envio',
        };
    }

    /** @return array<string, string> */
    public function credentialLabels(): array
    {
        return match ($this) {
            self::Frenet => [
                'client_token' => 'Token do cliente',
                'partner_token' => 'Partner Token',
            ],
            self::MelhorEnvio => [
                'client_id' => 'Client ID do aplicativo',
                'client_secret' => 'Client Secret do aplicativo',
            ],
        };
    }

    /** @return list<string> */
    public function requiredCredentialKeys(): array
    {
        return array_keys($this->credentialLabels());
    }
}
