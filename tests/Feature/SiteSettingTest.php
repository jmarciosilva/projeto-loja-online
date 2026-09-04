<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class SiteSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_scalar_setting_can_be_persisted_and_retrieved_by_key(): void
    {
        SiteSetting::create([
            'key' => 'store.name',
            'value' => 'Loja Online',
            'type' => 'string',
        ]);

        $setting = SiteSetting::query()->where('key', 'store.name')->firstOrFail();

        $this->assertSame('Loja Online', $setting->value);
        $this->assertIsString($setting->value);
    }

    public function test_the_key_must_be_unique(): void
    {
        SiteSetting::create([
            'key' => 'store.email',
            'type' => 'string',
            'value' => 'first@example.com',
        ]);

        $this->expectException(QueryException::class);

        SiteSetting::create([
            'key' => 'store.email',
            'type' => 'string',
            'value' => 'second@example.com',
        ]);
    }

    public function test_integer_values_are_retrieved_as_integers(): void
    {
        $setting = SiteSetting::create([
            'key' => 'shipping.free_from',
            'type' => 'integer',
            'value' => 150,
        ]);

        $this->assertSame(150, $setting->fresh()->value);
        $this->assertIsInt($setting->fresh()->value);
    }

    public function test_boolean_values_are_retrieved_as_booleans(): void
    {
        $setting = SiteSetting::create([
            'key' => 'store.maintenance',
            'type' => 'boolean',
            'value' => false,
        ]);

        $this->assertFalse($setting->fresh()->value);
        $this->assertIsBool($setting->fresh()->value);
    }

    public function test_structured_values_are_retrieved_as_arrays(): void
    {
        $value = [
            'street' => 'Rua das Flores',
            'number' => 123,
            'city' => 'São Paulo',
        ];

        $setting = SiteSetting::create([
            'key' => 'store.address',
            'type' => 'json',
            'value' => $value,
        ]);

        $this->assertSame($value, $setting->fresh()->value);
        $this->assertIsArray($setting->fresh()->value);
    }

    public function test_an_updated_setting_keeps_its_type_contract(): void
    {
        $setting = SiteSetting::create([
            'key' => 'store.featured_limit',
            'type' => 'integer',
            'value' => 6,
        ]);

        $setting->update(['value' => 12]);

        $updatedSetting = $setting->fresh();

        $this->assertSame('integer', $updatedSetting->type);
        $this->assertSame(12, $updatedSetting->value);
        $this->assertIsInt($updatedSetting->value);
    }

    public function test_null_values_are_explicitly_supported(): void
    {
        $setting = SiteSetting::create([
            'key' => 'store.logo',
            'type' => 'null',
            'value' => null,
        ]);

        $this->assertSame('null', $setting->fresh()->type);
        $this->assertNull($setting->fresh()->value);
    }

    public function test_unsupported_types_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SiteSetting::create([
            'key' => 'store.timezone',
            'type' => 'timezone',
            'value' => 'America/Sao_Paulo',
        ]);
    }

    public function test_values_incompatible_with_their_type_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SiteSetting::create([
            'key' => 'store.products_per_page',
            'type' => 'integer',
            'value' => 'twelve',
        ]);
    }

    public function test_changing_only_the_type_is_rejected_without_persisting_it(): void
    {
        $setting = SiteSetting::create([
            'key' => 'store.name',
            'type' => 'string',
            'value' => 'Loja Online',
        ]);

        try {
            $setting->update(['type' => 'json']);
            $this->fail('Changing only a setting type should be rejected.');
        } catch (LogicException) {
            $persistedSetting = $setting->fresh();

            $this->assertSame('string', $persistedSetting->type);
            $this->assertSame('Loja Online', $persistedSetting->value);
        }
    }

    public function test_changing_type_with_a_value_that_serializes_to_the_same_value_is_allowed(): void
    {
        $setting = SiteSetting::create([
            'key' => 'store.feature_enabled',
            'type' => 'string',
            'value' => '1',
        ]);

        $setting->update([
            'type' => 'boolean',
            'value' => true,
        ]);

        $updatedSetting = $setting->fresh();

        $this->assertSame('boolean', $updatedSetting->type);
        $this->assertTrue($updatedSetting->value);
    }

    public function test_an_invalid_value_does_not_leave_authorization_to_change_type(): void
    {
        $setting = SiteSetting::create([
            'key' => 'store.name',
            'type' => 'string',
            'value' => 'abc',
        ]);

        try {
            $setting->update([
                'type' => 'integer',
                'value' => 'invalid',
            ]);
            $this->fail('An incompatible value should be rejected.');
        } catch (InvalidArgumentException) {
            try {
                $setting->save();
                $this->fail('A failed value assignment must not authorize a type change.');
            } catch (LogicException) {
                $persistedSetting = $setting->fresh();

                $this->assertSame('string', $persistedSetting->type);
                $this->assertSame('abc', $persistedSetting->value);
            }
        }
    }
}
