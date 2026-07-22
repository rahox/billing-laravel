<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Modul yang tersedia di sistem billing. Setiap modul punya 4 permission: view/create/update/delete.
     */
    private const MODULES = [
        'customers', 'products', 'discounts', 'transactions', 'invoices',
        'delivery_orders', 'payments', 'expenses', 'journal', 'reports', 'users',
    ];

    public function run(): void
    {
        $permissions = [];
        foreach (self::MODULES as $module) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                $permissions[] = "{$module}.{$action}";
            }
        }

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        $reseller = Role::firstOrCreate(['name' => 'reseller', 'guard_name' => 'web']);
        $reseller->syncPermissions([
            'customers.view', 'customers.create', 'customers.update',
            'products.view', 'discounts.view',
            'transactions.view', 'transactions.create', 'transactions.update',
            'invoices.view', 'invoices.create',
            'delivery_orders.view', 'delivery_orders.create', 'delivery_orders.update',
            'payments.view',
            'reports.view',
        ]);

        $sales = Role::firstOrCreate(['name' => 'sales', 'guard_name' => 'web']);
        $sales->syncPermissions([
            'customers.view', 'customers.create',
            'products.view',
            'invoices.view',
            'delivery_orders.view',
            'reports.view',
        ]);

        $collector = Role::firstOrCreate(['name' => 'collector', 'guard_name' => 'web']);
        $collector->syncPermissions([
            'customers.view',
            'invoices.view',
            'delivery_orders.view',
            'payments.view', 'payments.create', 'payments.update',
            'reports.view',
        ]);
    }
}
