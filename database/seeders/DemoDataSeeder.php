<?php

namespace Database\Seeders;

use App\Models\Discount;
use App\Models\Expense;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\Concerns\GeneratesCustomerBilling;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoDataSeeder extends Seeder
{
    use GeneratesCustomerBilling;

    public function __construct()
    {
        $this->initGenerators();
    }

    public function run(): void
    {
        $start = Carbon::create(2026, 1, 1);
        $today = Carbon::now();

        $this->journal->postCapitalInjection($start->toDateString(), 300_000_000, 'Setoran modal awal pemilik ISP');

        $team = $this->createResellersAndTeam();
        $products = $this->createProducts();
        $discounts = $this->createDiscounts();

        $this->generateCustomersAndBilling($start, $today, $team, $products, $discounts);
        $this->generateExpenses($start, $today);
    }

    /**
     * @return array{resellers: \Illuminate\Support\Collection, sales: \Illuminate\Support\Collection, collectors: \Illuminate\Support\Collection}
     */
    private function createResellersAndTeam(): array
    {
        $resellerNames = ['Reseller Jaya Net', 'Reseller Mitra Koneksi', 'Reseller Cahaya Digital'];
        $resellers = collect();
        $sales = collect();

        foreach ($resellerNames as $i => $name) {
            $reseller = User::firstOrCreate(
                ['email' => 'reseller'.($i + 1).'@bill-isp.test'],
                ['name' => $name, 'password' => 'password', 'phone' => $this->randomPhone(), 'is_active' => true]
            );
            $reseller->assignRole('reseller');
            $resellers->push($reseller);

            foreach ([1, 2] as $n) {
                $commissionType = $n === 1 ? 'percentage' : 'flat';
                $salesUser = User::firstOrCreate(
                    ['email' => "sales{$i}{$n}@bill-isp.test"],
                    [
                        'name' => $this->faker->name(),
                        'password' => 'password',
                        'phone' => $this->randomPhone(),
                        'parent_reseller_id' => $reseller->id,
                        'commission_type' => $commissionType,
                        'commission_value' => $commissionType === 'percentage' ? 5 : 25000,
                        'is_active' => true,
                    ]
                );
                $salesUser->assignRole('sales');
                $sales->push($salesUser);
            }
        }

        $collectors = collect();
        foreach (['Kolektor Wilayah Utara', 'Kolektor Wilayah Selatan'] as $i => $name) {
            $collector = User::firstOrCreate(
                ['email' => 'collector'.($i + 1).'@bill-isp.test'],
                ['name' => $name, 'password' => 'password', 'phone' => $this->randomPhone(), 'is_active' => true]
            );
            $collector->assignRole('collector');
            $collectors->push($collector);
        }

        return ['resellers' => $resellers, 'sales' => $sales, 'collectors' => $collectors];
    }

    private function createProducts(): \Illuminate\Support\Collection
    {
        $items = [
            ['code' => 'INT-10M', 'name' => 'Internet Rumahan 10 Mbps', 'type' => 'jasa', 'price' => 250000, 'cost_price' => 60000, 'is_recurring' => true, 'recurring_period' => 'monthly', 'is_ppn_applicable' => true, 'is_telco_levy_applicable' => true],
            ['code' => 'INT-20M', 'name' => 'Internet Rumahan 20 Mbps', 'type' => 'jasa', 'price' => 350000, 'cost_price' => 85000, 'is_recurring' => true, 'recurring_period' => 'monthly', 'is_ppn_applicable' => true, 'is_telco_levy_applicable' => true],
            ['code' => 'INT-50M-BIZ', 'name' => 'Internet Bisnis 50 Mbps', 'type' => 'jasa', 'price' => 1500000, 'cost_price' => 400000, 'is_recurring' => true, 'recurring_period' => 'monthly', 'is_ppn_applicable' => true, 'is_telco_levy_applicable' => true],
            ['code' => 'INST-BARU', 'name' => 'Biaya Instalasi/Pemasangan Baru', 'type' => 'jasa', 'price' => 150000, 'cost_price' => 50000, 'is_recurring' => false, 'recurring_period' => null, 'is_ppn_applicable' => true, 'is_telco_levy_applicable' => false],
            ['code' => 'RTR-WIFI', 'name' => 'Router WiFi Home', 'type' => 'barang', 'price' => 350000, 'cost_price' => 250000, 'is_recurring' => false, 'recurring_period' => null, 'is_ppn_applicable' => true, 'is_telco_levy_applicable' => false],
            ['code' => 'ONT-MODEM', 'name' => 'ONT/Modem Fiber', 'type' => 'barang', 'price' => 300000, 'cost_price' => 200000, 'is_recurring' => false, 'recurring_period' => null, 'is_ppn_applicable' => true, 'is_telco_levy_applicable' => false],
        ];

        return collect($items)->map(fn ($item) => Product::updateOrCreate(['code' => $item['code']], $item + ['is_active' => true]));
    }

    private function createDiscounts(): \Illuminate\Support\Collection
    {
        $items = [
            ['name' => 'Diskon Promo 10%', 'type' => 'percentage', 'mode' => 'manual', 'value' => 10, 'description' => 'Diskon promo umum, dipilih manual saat transaksi'],
            ['name' => 'Diskon Pelanggan Lama Rp20.000', 'type' => 'flat', 'mode' => 'manual', 'value' => 20000, 'description' => 'Potongan nominal tetap, dipilih manual'],
            ['name' => 'Prorate Aktivasi Awal', 'type' => 'percentage', 'mode' => 'prorate_activation', 'value' => 0, 'description' => 'Otomatis dihitung dari sisa hari periode saat aktivasi awal'],
        ];

        return collect($items)->map(fn ($item) => Discount::updateOrCreate(['name' => $item['name']], $item + ['is_active' => true]));
    }

    private function generateCustomersAndBilling(Carbon $start, Carbon $today, array $team, \Illuminate\Support\Collection $products, \Illuminate\Support\Collection $discounts): void
    {
        $resellers = $team['resellers'];
        $collectors = $team['collectors'];
        $internetProducts = $products->whereIn('code', ['INT-10M', 'INT-20M', 'INT-50M-BIZ'])->values();
        $hardwareProducts = $products->whereIn('code', ['RTR-WIFI', 'ONT-MODEM'])->values();
        $installationProduct = $products->firstWhere('code', 'INST-BARU');
        $prorateDiscount = $discounts->firstWhere('mode', 'prorate_activation');
        $manualDiscounts = $discounts->where('mode', 'manual')->values();

        $newPerMonth = [25, 18, 20, 15, 22, 18, 10];
        $customerSeq = 0;
        $allCustomers = collect();

        for ($m = 0; $m < 7; $m++) {
            $monthStart = $start->copy()->addMonths($m);
            if ($monthStart->greaterThan($today)) {
                break;
            }
            $monthEnd = $monthStart->copy()->endOfMonth();
            $countNew = $newPerMonth[$m];

            for ($c = 0; $c < $countNew; $c++) {
                $customerSeq++;
                $reseller = $resellers->random();
                $salesCandidates = $team['sales']->where('parent_reseller_id', $reseller->id)->values();
                $sales = $salesCandidates->random();
                $collector = $collectors->random();

                $lastActivationDay = $monthEnd->lessThan($today) ? $monthEnd->day : $today->day;
                $activationDate = $monthStart->copy()->addDays(random_int(0, max(0, $lastActivationDay - 1)));

                $customer = \App\Models\Customer::create([
                    'customer_number' => 'CUST-'.str_pad((string) $customerSeq, 6, '0', STR_PAD_LEFT),
                    'name' => $this->faker->name(),
                    'address' => $this->faker->address(),
                    'phone1' => $this->randomPhone(),
                    'phone2' => random_int(0, 100) < 40 ? $this->randomPhone() : null,
                    'reseller_id' => $reseller->id,
                    'sales_id' => $sales->id,
                    'collector_id' => $collector->id,
                    'activation_date' => $activationDate->toDateString(),
                    'status' => 'active',
                ]);

                $product = $internetProducts[$this->weightedIndex([50, 35, 15], count($internetProducts))];
                $buysHardware = random_int(0, 100) < 55;
                $hardwareProduct = $buysHardware ? $hardwareProducts->random() : null;

                $allCustomers->push([
                    'customer' => $customer,
                    'product' => $product,
                    'hardware' => $hardwareProduct,
                    'installation_billed' => false,
                ]);
            }
        }

        foreach ($allCustomers as $entry) {
            $this->billCustomerHistory($entry, $today, $installationProduct, $prorateDiscount, $manualDiscounts);
        }
    }

    private function generateExpenses(Carbon $start, Carbon $today): void
    {
        $categories = [
            'bandwidth_upstream' => [8_000_000, 15_000_000],
            'sewa_tower_kolokasi' => [3_000_000, 5_000_000],
            'listrik' => [1_500_000, 3_000_000],
            'gaji_karyawan' => [25_000_000, 35_000_000],
            'sewa_kantor' => [2_000_000, 2_000_000],
            'internet_kantor' => [500_000, 800_000],
            'transportasi_operasional' => [800_000, 2_000_000],
            'pemeliharaan_jaringan' => [1_000_000, 4_000_000],
            'marketing' => [500_000, 3_000_000],
        ];

        $seq = 0;
        $cursor = $start->copy();
        while ($cursor->lessThanOrEqualTo($today)) {
            $maxDay = max(1, $cursor->copy()->endOfMonth()->day - 1);

            foreach ($categories as $category => [$min, $max]) {
                $seq++;
                $expenseDate = $cursor->copy()->addDays(random_int(1, $maxDay))->min($today);

                $expense = Expense::create([
                    'expense_number' => 'EXP-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
                    'category' => $category,
                    'vendor' => $this->vendorFor($category),
                    'description' => $this->descriptionFor($category, $expenseDate),
                    'amount' => random_int($min, $max),
                    'expense_date' => $expenseDate->toDateString(),
                ]);
                $this->journal->postExpense($expense);
            }

            // Pembelian perangkat jaringan (capex) sesekali, tidak setiap bulan
            if (random_int(0, 100) < 40) {
                $seq++;
                $expenseDate = $cursor->copy()->addDays(random_int(1, $maxDay))->min($today);
                $expense = Expense::create([
                    'expense_number' => 'EXP-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
                    'category' => 'perangkat_jaringan',
                    'vendor' => 'PT Sumber Perangkat Telekomunikasi',
                    'description' => 'Pembelian perangkat OLT/switch jaringan',
                    'amount' => random_int(10_000_000, 40_000_000),
                    'expense_date' => $expenseDate->toDateString(),
                ]);
                $this->journal->postExpense($expense);
            }

            $cursor = $cursor->copy()->addMonthNoOverflow()->startOfMonth();
        }
    }

    private function vendorFor(string $category): string
    {
        return match ($category) {
            'bandwidth_upstream' => 'PT NAP Indonesia Bandwidth',
            'sewa_tower_kolokasi' => 'PT Menara Telekomunikasi',
            'listrik' => 'PLN',
            'gaji_karyawan' => 'Internal Payroll',
            'sewa_kantor' => 'Pemilik Ruko',
            'internet_kantor' => 'ISP Upstream Kantor',
            'transportasi_operasional' => 'Operasional Teknisi',
            'pemeliharaan_jaringan' => 'Vendor Maintenance',
            'marketing' => 'Vendor Promosi',
            default => 'Lainnya',
        };
    }

    private function descriptionFor(string $category, Carbon $date): string
    {
        $labels = [
            'bandwidth_upstream' => 'Sewa bandwidth upstream bulan '.$date->translatedFormat('F Y'),
            'sewa_tower_kolokasi' => 'Sewa tower & kolokasi bulan '.$date->translatedFormat('F Y'),
            'listrik' => 'Tagihan listrik NOC & tower bulan '.$date->translatedFormat('F Y'),
            'gaji_karyawan' => 'Gaji karyawan bulan '.$date->translatedFormat('F Y'),
            'sewa_kantor' => 'Sewa kantor bulan '.$date->translatedFormat('F Y'),
            'internet_kantor' => 'Internet kantor bulan '.$date->translatedFormat('F Y'),
            'transportasi_operasional' => 'Transportasi teknisi & operasional lapangan',
            'pemeliharaan_jaringan' => 'Pemeliharaan & perbaikan jaringan',
            'marketing' => 'Biaya promosi & marketing',
        ];

        return $labels[$category] ?? ucfirst($category);
    }
}
