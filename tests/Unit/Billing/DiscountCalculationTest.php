<?php

namespace Tests\Unit\Billing;

use App\Models\Discount;
use Tests\TestCase;

class DiscountCalculationTest extends TestCase
{
    public function test_percentage_discount(): void
    {
        $discount = new Discount(['type' => 'percentage', 'mode' => 'manual', 'value' => 15]);

        $this->assertSame(15000.0, $discount->calculateAmount(100000));
    }

    public function test_flat_discount_capped_at_subtotal(): void
    {
        $discount = new Discount(['type' => 'flat', 'mode' => 'manual', 'value' => 50000]);

        $this->assertSame(30000.0, $discount->calculateAmount(30000));
        $this->assertSame(50000.0, $discount->calculateAmount(100000));
    }

    public function test_prorate_activation_discounts_unused_days(): void
    {
        $discount = new Discount(['type' => 'percentage', 'mode' => 'prorate_activation', 'value' => 0]);

        // 30-day period, customer only used the last 10 days -> 20 unused days discounted.
        $amount = $discount->calculateAmount(300000, ['days_in_period' => 30, 'days_used' => 10]);

        $this->assertSame(200000.0, $amount);
    }

    public function test_prorate_activation_with_full_period_used_has_no_discount(): void
    {
        $discount = new Discount(['type' => 'percentage', 'mode' => 'prorate_activation', 'value' => 0]);

        $amount = $discount->calculateAmount(300000, ['days_in_period' => 30, 'days_used' => 30]);

        $this->assertSame(0.0, $amount);
    }
}
