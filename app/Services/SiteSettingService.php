<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Contracts\Cache\Repository;

class SiteSettingService
{
    public const CACHE_TTL_SECONDS = 300;

    public function __construct(private readonly Repository $cache) {}

    /**
     * Obtém uma configuração tipada. O envelope em cache preserva a diferença
     * entre uma chave ausente e uma configuração existente cujo valor é null.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        /** @var array{exists: bool, value?: mixed} $cachedSetting */
        $cachedSetting = $this->cache->remember(
            $this->cacheKey($key),
            now()->addSeconds(self::CACHE_TTL_SECONDS),
            function () use ($key): array {
                $setting = SiteSetting::query()->where('key', $key)->first();

                if ($setting === null) {
                    return ['exists' => false];
                }

                return [
                    'exists' => true,
                    'value' => $setting->value,
                ];
            },
        );

        return $cachedSetting['exists'] ? $cachedSetting['value'] : $default;
    }

    /**
     * Cria ou atualiza a configuração e invalida sua entrada em cache após a persistência.
     */
    public function set(string $key, string $type, mixed $value): SiteSetting
    {
        $setting = SiteSetting::query()->firstOrNew(['key' => $key]);
        $setting->fill([
            'type' => $type,
            'value' => $value,
        ]);
        $setting->save();

        $this->cache->forget($this->cacheKey($key));

        return $setting;
    }

    private function cacheKey(string $key): string
    {
        return "site_settings:{$key}";
    }
}
