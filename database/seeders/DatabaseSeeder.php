<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            ChartOfAccountSeeder::class,
        ]);

        $owner = User::firstOrCreate(
            ['email' => 'owner@bill-isp.test'],
            [
                'name' => 'Super Admin',
                'password' => 'password',
                'phone' => '081234500001',
                'is_active' => true,
            ]
        );
        $owner->assignRole('super-admin');

        $this->call([
            DemoDataSeeder::class,
        ]);
    }
}
