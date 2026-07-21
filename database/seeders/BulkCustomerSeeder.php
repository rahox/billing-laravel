<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Discount;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\Concerns\GeneratesCustomerBilling;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tambahkan pelanggan dalam jumlah besar untuk uji performa (skala nyata), dengan riwayat
 * billing yang sengaja dibuat pendek (aktivasi dalam ~60 hari terakhir) supaya proses
 * generate tetap cepat walau jumlah pelanggannya besar.
 *
 * Prasyarat: RoleSeeder, ChartOfAccountSeeder, dan DemoDataSeeder sudah pernah dijalankan
 * (butuh reseller/sales/collector/produk/diskon yang sudah ada).
 *
 * Jumlah pelanggan diatur lewat env BULK_CUSTOMER_COUNT (default 16000):
 *   BULK_CUSTOMER_COUNT=5000 php artisan db:seed --class=Database\\Seeders\\BulkCustomerSeeder
 */
class BulkCustomerSeeder extends Seeder
{
    use GeneratesCustomerBilling;

    public function __construct()
    {
        $this->initGenerators();
    }

    public function run(): void
    {
        $count = (int) env('BULK_CUSTOMER_COUNT', 16000);
        $today = Carbon::now();

        $resellers = User::role('reseller')->get();
        $sales = User::role('sales')->get();
        $collectors = User::role('collector')->get();
        $products = Product::all();
        $discounts = Discount::all();

        if ($resellers->isEmpty() || $sales->isEmpty() || $collectors->isEmpty() || $products->isEmpty() || $discounts->isEmpty()) {
            $this->command?->warn('Jalankan RoleSeeder, ChartOfAccountSeeder, dan DemoDataSeeder dulu sebelum BulkCustomerSeeder.');

            return;
        }

        $internetProducts = $products->whereIn('code', ['INT-10M', 'INT-20M', 'INT-50M-BIZ'])->values();
        $hardwareProducts = $products->whereIn('code', ['RTR-WIFI', 'ONT-MODEM'])->values();
        $installationProduct = $products->firstWhere('code', 'INST-BARU');
        $prorateDiscount = $discounts->firstWhere('mode', 'prorate_activation');
        $manualDiscounts = $discounts->where('mode', 'manual')->values();

        $nextSeq = ((int) DB::table('customers')->max(DB::raw('CAST(SUBSTR(customer_number, 6) AS INTEGER)'))) + 1;

        $bar = $this->command?->getOutput()?->createProgressBar($count);
        $bar?->start();

        for ($i = 0; $i < $count; $i++) {
            $reseller = $resellers->random();
            $salesCandidates = $sales->where('parent_reseller_id', $reseller->id)->values();
            $salesUser = $salesCandidates->isNotEmpty() ? $salesCandidates->random() : $sales->random();
            $collector = $collectors->random();

            // Aktivasi tersebar di 60 hari terakhir -> riwayat billing otomatis pendek (1-2 bulan).
            $activationDate = $today->copy()->subDays(random_int(0, 60));

            $customer = Customer::create([
                'customer_number' => 'CUST-'.str_pad((string) ($nextSeq + $i), 6, '0', STR_PAD_LEFT),
                'name' => $this->faker->name(),
                'address' => $this->faker->address(),
                'phone1' => $this->randomPhone(),
                'phone2' => random_int(0, 100) < 40 ? $this->randomPhone() : null,
                'reseller_id' => $reseller->id,
                'sales_id' => $salesUser->id,
                'collector_id' => $collector->id,
                'activation_date' => $activationDate->toDateString(),
                'status' => 'active',
            ]);

            $product = $internetProducts[$this->weightedIndex([50, 35, 15], count($internetProducts))];
            $hardwareProduct = random_int(0, 100) < 55 ? $hardwareProducts->random() : null;

            $this->billCustomerHistory(
                ['customer' => $customer, 'product' => $product, 'hardware' => $hardwareProduct],
                $today,
                $installationProduct,
                $prorateDiscount,
                $manualDiscounts,
            );

            $bar?->advance();
        }

        $bar?->finish();
        $this->command?->newLine();
    }
}
