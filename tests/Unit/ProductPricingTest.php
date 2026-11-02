<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\ProductService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ProductPricingTest extends TestCase
{
    public function test_only_an_enabled_promotion_inside_its_period_changes_the_effective_price(): void
    {
        $start = CarbonImmutable::parse('2026-11-08 10:00:00', config('app.timezone'));
        $end = CarbonImmutable::parse('2026-11-10 10:00:00', config('app.timezone'));
        $product = new Product([
            'price' => '59.90',
            'promotional_price' => '49.90',
            'promotion_enabled' => true,
            'promotion_starts_at' => $start,
            'promotion_ends_at' => $end,
        ]);

        $this->assertSame('59.90', $product->effectivePrice($start->subSecond()));
        $this->assertSame('49.90', $product->effectivePrice($start));
        $this->assertSame('49.90', $product->effectivePrice($end->subSecond()));
        $this->assertSame('59.90', $product->effectivePrice($end));
    }

    public function test_disabled_future_expired_and_legacy_promotions_keep_the_regular_price(): void
    {
        $now = CarbonImmutable::parse('2026-11-08 10:00:00', config('app.timezone'));
        $disabled = new Product([
            'price' => '59.90', 'promotional_price' => '49.90', 'promotion_enabled' => false,
        ]);
        $future = new Product([
            'price' => '59.90', 'promotional_price' => '49.90', 'promotion_enabled' => true,
            'promotion_starts_at' => $now->addMinute(),
        ]);
        $expired = new Product([
            'price' => '59.90', 'promotional_price' => '49.90', 'promotion_enabled' => true,
            'promotion_ends_at' => $now,
        ]);

        $this->assertSame('59.90', $disabled->effectivePrice($now));
        $this->assertSame('59.90', $future->effectivePrice($now));
        $this->assertSame('59.90', $expired->effectivePrice($now));
    }

    public function test_margin_formula_and_rounding_use_integer_cents(): void
    {
        $this->assertSame('50.00', ProductService::suggestedPrice('30,00', '40'));
        $this->assertSame('0.02', ProductService::suggestedPrice('0,01', '33,34'));
        $this->assertSame('R$ 1.234.567,89', ProductService::formatPrice('1234567.89'));
        $this->assertSame('R$ 59,90', ProductService::formatPrice('59.9'));
    }

    public function test_promotion_discount_and_effective_margin_use_half_up_rounding(): void
    {
        $this->assertSame('45.00', ProductService::suggestedPromotionPrice('50,00', '10'));
        $this->assertSame('0.05', ProductService::suggestedPromotionPrice('0,05', '10'));
        $this->assertSame('40.00', ProductService::effectiveMargin('30.00', '50.00'));
        $this->assertSame('-20.00', ProductService::effectiveMargin('60.00', '50.00'));
    }

    public function test_margin_equal_to_or_above_one_hundred_percent_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ProductService::suggestedPrice('30.00', '100');
    }

    public function test_promotion_dates_use_the_configured_brazilian_timezone(): void
    {
        $localTime = CarbonImmutable::parse('2026-11-08 10:00:00', config('app.timezone'));

        $this->assertSame(config('app.timezone'), $localTime->getTimezone()->getName());
        $this->assertTrue((new Product([
            'price' => '50.00',
            'promotional_price' => '45.00',
            'promotion_enabled' => true,
            'promotion_starts_at' => $localTime,
        ]))->promotionIsActive($localTime));
    }
}
