<?php

namespace App\Services;

/** Configurações públicas da marca sem persistir defaults durante a leitura. */
class StorefrontBrandingService
{
    public const DEFAULT_NAME = 'UAU NetShopping';

    public const DEFAULT_SLOGAN = 'Variedade que surpreende!';

    public function __construct(private readonly SiteSettingService $settings) {}

    /** @return array{name: string, slogan: string} */
    public function brand(): array
    {
        return [
            'name' => $this->stringSetting('site.name', self::DEFAULT_NAME),
            'slogan' => $this->stringSetting('site.slogan', self::DEFAULT_SLOGAN),
        ];
    }

    /** @return array{email: string, phone: string, address: string} */
    public function contact(): array
    {
        return [
            'email' => $this->stringSetting('site.support_email'),
            'phone' => $this->stringSetting('site.phone'),
            'address' => $this->stringSetting('site.address'),
        ];
    }

    private function stringSetting(string $key, string $default = ''): string
    {
        $value = $this->settings->get($key, $default);

        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }
}
