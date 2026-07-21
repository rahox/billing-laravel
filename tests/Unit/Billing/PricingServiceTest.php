<?php

namespace Tests\Unit\Billing;

use App\Services\Billing\PricingService;
use Tests\Concerns\CreatesBillingData;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PricingServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBillingData;

    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = new PricingService;
    }

    public function test_internet_product_line_applies_ppn_bhp_and_uso(): void
    {
        $product = $this->createInternetProduct(['price' => 200000]);

        $calc = $this->pricing->calculateLine($product, 1);

        $this->assertSame(200000.0, $calc['subtotal']);
        $this->assertSame(22000.0, $calc['ppn_amount']); // 11%
        $this->assertSame(500.0, $calc['bhp_amount']); // 0.25%
        $this->assertSame(2500.0, $calc['uso_amount']); // 1.25%
        // BHP/USO are internal charges, not billed to the customer.
        $this->assertSame(222000.0, $calc['total']);
    }

    public function test_device_product_line_has_no_bhp_or_uso(): void
    {
        $product = $this->createDeviceProduct(['price' => 350000]);

        $calc = $this->pricing->calculateLine($product, 1);

        $this->assertSame(0.0, $calc['bhp_amount']);
        $this->assertSame(0.0, $calc['uso_amount']);
        $this->assertSame(38500.0, $calc['ppn_amount']); // 11%
        $this->assertSame(388500.0, $calc['total']);
    }

    public function test_quantity_multiplies_unit_price_before_discount(): void
    {
        $product = $this->createDeviceProduct(['price' => 100000]);

        $calc = $this->pricing->calculateLine($product, 3);

        $this->assertSame(300000.0, $calc['subtotal']);
    }

    public function test_percentage_discount_reduces_subtotal_before_tax(): void
    {
        $product = $this->createInternetProduct(['price' => 200000]);
        $discount = $this->createDiscount(['type' => 'percentage', 'mode' => 'manual', 'value' => 10]);

        $calc = $this->pricing->calculateLine($product, 1, $discount);

        $this->assertSame(20000.0, $calc['discount_amount']);
        $this->assertSame(180000.0, $calc['subtotal']);
        $this->assertSame(19800.0, $calc['ppn_amount']); // 11% of discounted subtotal
    }

    public function test_flat_discount_cannot_exceed_subtotal(): void
    {
        $product = $this->createDeviceProduct(['price' => 50000]);
        $discount = $this->createDiscount(['type' => 'flat', 'mode' => 'manual', 'value' => 999999]);

        $calc = $this->pricing->calculateLine($product, 1, $discount);

        $this->assertSame(50000.0, $calc['discount_amount']);
        $this->assertSame(0.0, $calc['subtotal']);
    }

    public function test_activation_proration_context_counts_remaining_days_in_period(): void
    {
        $periodStart = new \DateTime('2026-07-01');
        $periodEnd = new \DateTime('2026-07-31');
        $activationDate = new \DateTime('2026-07-21');

        $context = $this->pricing->activationProrationContext($periodStart, $periodEnd, $activationDate);

        $this->assertSame(31, $context['days_in_period']);
        $this->assertSame(11, $context['days_used']); // 21st through 31st inclusive
    }
}
