<?php

namespace Tests\Concerns;

use App\Models\Customer;
use App\Models\Discount;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\ChartOfAccountSeeder;
use Database\Seeders\RoleSeeder;

trait CreatesBillingData
{
    protected function seedRolesAndAccounts(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(ChartOfAccountSeeder::class);
    }

    protected function createUserWithRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }

    protected function createInternetProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'code' => 'PRD-'.fake()->unique()->numerify('####'),
            'name' => 'Internet Home 20 Mbps',
            'type' => 'jasa',
            'price' => 200000,
            'cost_price' => 0,
            'is_recurring' => true,
            'recurring_period' => 'monthly',
            'is_ppn_applicable' => true,
            'is_telco_levy_applicable' => true,
            'is_active' => true,
        ], $attributes));
    }

    protected function createDeviceProduct(array $attributes = []): Product
    {
        return Product::create(array_merge([
            'code' => 'DEV-'.fake()->unique()->numerify('####'),
            'name' => 'Router WiFi',
            'type' => 'barang',
            'price' => 350000,
            'cost_price' => 250000,
            'is_recurring' => false,
            'is_ppn_applicable' => true,
            'is_telco_levy_applicable' => false,
            'is_active' => true,
        ], $attributes));
    }

    protected function createCustomer(array $attributes = []): Customer
    {
        return Customer::create(array_merge([
            'customer_number' => 'CUST-'.fake()->unique()->numerify('######'),
            'name' => fake()->name(),
            'address' => fake()->address(),
            'phone1' => fake()->numerify('08##########'),
            'status' => 'active',
        ], $attributes));
    }

    protected function createDiscount(array $attributes = []): Discount
    {
        return Discount::create(array_merge([
            'name' => 'Diskon Test',
            'type' => 'percentage',
            'mode' => 'manual',
            'value' => 10,
            'is_active' => true,
        ], $attributes));
    }
}
