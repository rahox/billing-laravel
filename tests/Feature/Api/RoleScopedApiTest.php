<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBillingData;
use Tests\TestCase;

class RoleScopedApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBillingData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();
    }

    public function test_super_admin_sees_all_customers(): void
    {
        $admin = $this->createUserWithRole('super-admin');
        $resellerA = $this->createUserWithRole('reseller');
        $resellerB = $this->createUserWithRole('reseller');
        $this->createCustomer(['reseller_id' => $resellerA->id]);
        $this->createCustomer(['reseller_id' => $resellerB->id]);

        $response = $this->actingAs($admin)->getJson('/api/customers');

        $response->assertOk();
        $this->assertSame(2, $response->json('total'));
    }

    public function test_reseller_only_sees_their_own_customers(): void
    {
        $resellerA = $this->createUserWithRole('reseller');
        $resellerB = $this->createUserWithRole('reseller');
        $this->createCustomer(['reseller_id' => $resellerA->id]);
        $this->createCustomer(['reseller_id' => $resellerB->id]);

        $response = $this->actingAs($resellerA)->getJson('/api/customers');

        $response->assertOk();
        $this->assertSame(1, $response->json('total'));
        $this->assertSame($resellerA->id, $response->json('data.0.reseller_id'));
    }

    public function test_sales_only_sees_customers_assigned_to_them(): void
    {
        $salesA = $this->createUserWithRole('sales');
        $salesB = $this->createUserWithRole('sales');
        $this->createCustomer(['sales_id' => $salesA->id]);
        $this->createCustomer(['sales_id' => $salesB->id]);
        $this->createCustomer(['sales_id' => $salesB->id]);

        $response = $this->actingAs($salesA)->getJson('/api/customers');

        $response->assertOk();
        $this->assertSame(1, $response->json('total'));
    }

    public function test_collector_cannot_create_customers(): void
    {
        $collector = $this->createUserWithRole('collector');

        $response = $this->actingAs($collector)->postJson('/api/customers', [
            'name' => 'Test Customer',
            'address' => 'Jl. Test No. 1',
            'phone1' => '081234567890',
        ]);

        $response->assertForbidden();
    }

    public function test_only_super_admin_can_create_products(): void
    {
        $sales = $this->createUserWithRole('sales');

        $response = $this->actingAs($sales)->postJson('/api/products', [
            'code' => 'PRD-0001',
            'name' => 'Internet 10 Mbps',
            'type' => 'jasa',
            'price' => 150000,
        ]);

        $response->assertForbidden();
    }

    public function test_only_super_admin_can_view_expenses(): void
    {
        $reseller = $this->createUserWithRole('reseller');

        $response = $this->actingAs($reseller)->getJson('/api/expenses');

        $response->assertForbidden();
    }

    public function test_sales_cannot_access_income_statement_report(): void
    {
        $sales = $this->createUserWithRole('sales');

        $response = $this->actingAs($sales)->getJson('/api/reports/income-statement');

        $response->assertForbidden();
    }
}
