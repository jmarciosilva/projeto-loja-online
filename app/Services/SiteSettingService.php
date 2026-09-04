<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\DB;

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
        $setting = $this->persistSetting($key, $type, $value);

        $this->cache->forget($this->cacheKey($key));

        return $setting;
    }

    /**
     * Persiste um lote de configurações de forma atômica.
     *
     * Se qualquer item do lote falhar, a transação sofre rollback inteira e a
     * exceção original é propagada — nenhum item permanece alterado. Por isso a
     * invalidação do cache é registrada em `afterCommit`: invalidar durante a
     * transação deixaria o cache inconsistente com um banco que voltou atrás.
     *
     * A invalidação é granular, uma chave por configuração; o banco permanece a
     * fonte de verdade e nenhuma falha de cache é silenciada.
     *
     * @param  array<string, array{type: string, value: mixed}>  $settings
     * @return array<string, SiteSetting>
     */
    public function setMany(array $settings): array
    {
        if ($settings === []) {
            return [];
        }

        return DB::transaction(function () use ($settings): array {
            $persistedSettings = [];

            foreach ($settings as $key => $setting) {
                $persistedSettings[$key] = $this->persistSetting(
                    $key,
                    $setting['type'],
                    $setting['value'],
                );
            }

            DB::connection()->afterCommit(function () use ($settings): void {
                foreach (array_keys($settings) as $key) {
                    $this->cache->forget($this->cacheKey($key));
                }
            });

            return $persistedSettings;
        });
    }

    /**
     * Grava uma única configuração sem tocar no cache.
     *
     * Compartilhado por `set()` e `setMany()`, que diferem apenas em quando —
     * e se — a invalidação acontece.
     */
    private function persistSetting(string $key, string $type, mixed $value): SiteSetting
    {
        $setting = SiteSetting::query()->firstOrNew(['key' => $key]);
        $setting->fill([
            'type' => $type,
            'value' => $value,
        ]);
        $setting->save();

        return $setting;
    }

    private function cacheKey(string $key): string
    {
        return "site_settings:{$key}";
    }
}
