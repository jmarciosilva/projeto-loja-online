<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\SiteSettingService;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
