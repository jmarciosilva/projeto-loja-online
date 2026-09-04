<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\SiteSettingService;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class SiteSettingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_it_creates_a_setting_and_returns_its_typed_value(): void
    {
        $setting = $this->service()->set('store.colors', 'json', [
            'primary' => '#123456',
        ]);

        $this->assertDatabaseHas('site_settings', [
            'key' => 'store.colors',
            'type' => 'json',
        ]);
        $this->assertSame(['primary' => '#123456'], $setting->fresh()->value);
        $this->assertSame(['primary' => '#123456'], $this->service()->get('store.colors'));
    }

    public function test_it_can_be_resolved_by_the_laravel_container(): void
    {
        $this->assertInstanceOf(SiteSettingService::class, app(SiteSettingService::class));
    }

    public function test_it_returns_the_default_for_an_unknown_key(): void
    {
        $this->assertSame('default value', $this->service()->get('store.unknown', 'default value'));
        $this->assertNull($this->service()->get('store.other-unknown'));
    }

    public function test_a_cached_null_value_is_not_confused_with_an_unknown_key(): void
    {
        $this->service()->set('store.logo', 'null', null);

        $this->assertNull($this->service()->get('store.logo', 'default value'));
        $this->assertSame('default value', $this->service()->get('store.unknown', 'default value'));
    }

    public function test_creating_a_setting_invalidates_a_cached_unknown_key(): void
    {
        $service = $this->service();

        $this->assertSame('default value', $service->get('store.tagline', 'default value'));

        $service->set('store.tagline', 'string', 'Sua loja online');

        $this->assertSame('Sua loja online', $service->get('store.tagline'));
    }

    public function test_the_second_read_uses_the_cached_value(): void
    {
        SiteSetting::create([
            'key' => 'store.name',
            'type' => 'string',
            'value' => 'Valor A',
        ]);

        $service = $this->service();

        $this->assertSame('Valor A', $service->get('store.name'));

        DB::table('site_settings')
            ->where('key', 'store.name')
            ->update(['value' => 'Valor B']);

        $this->assertSame('Valor A', $service->get('store.name'));
    }

    public function test_updating_through_the_service_invalidates_the_cached_value(): void
    {
        $service = $this->service();
        $service->set('store.name', 'string', 'Valor A');

        $this->assertSame('Valor A', $service->get('store.name'));

        $updatedSetting = $service->set('store.name', 'string', 'Valor B');

        $this->assertSame(1, SiteSetting::query()->where('key', 'store.name')->count());
        $this->assertSame('Valor B', $updatedSetting->value);
        $this->assertSame('Valor B', $service->get('store.name'));
    }

    public function test_it_preserves_all_contract_types_through_the_cache(): void
    {
        $settings = [
            ['key' => 'store.name', 'type' => 'string', 'value' => 'Loja Online'],
            ['key' => 'store.products_per_page', 'type' => 'integer', 'value' => 12],
            ['key' => 'store.maintenance', 'type' => 'boolean', 'value' => false],
            ['key' => 'store.colors', 'type' => 'json', 'value' => ['primary' => '#123456']],
            ['key' => 'store.logo', 'type' => 'null', 'value' => null],
        ];
        $service = $this->service();

        foreach ($settings as $setting) {
            $service->set($setting['key'], $setting['type'], $setting['value']);

            $this->assertSame($setting['value'], $service->get($setting['key']));
            $this->assertSame($setting['value'], $service->get($setting['key']));
        }
    }

    public function test_an_invalid_write_does_not_invalidate_a_valid_cached_value(): void
    {
        $service = $this->service();
        $service->set('store.name', 'string', 'Valor A');

        $this->assertSame('Valor A', $service->get('store.name'));

        try {
            $service->set('store.name', 'integer', 'invalid');
            $this->fail('An incompatible value should be rejected.');
        } catch (InvalidArgumentException) {
            DB::table('site_settings')
                ->where('key', 'store.name')
                ->update(['value' => 'Valor B']);

            $this->assertSame('Valor A', $service->get('store.name'));
        }
    }

    public function test_the_cache_ttl_is_five_minutes(): void
    {
        $this->assertSame(300, SiteSettingService::CACHE_TTL_SECONDS);
    }

    private function service(): SiteSettingService
    {
        /** @var Repository $cache */
        $cache = Cache::store();

        return new SiteSettingService($cache);
    }
}
