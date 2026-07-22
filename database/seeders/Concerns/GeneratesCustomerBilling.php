<?php

namespace Database\Seeders\Concerns;

use App\Models\Discount;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\Accounting\JournalPostingService;
use App\Services\Billing\InvoiceBuilder;
use App\Services\Billing\PaymentService;
use App\Services\Billing\TransactionService;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Support\Carbon;

/**
 * Logika pembentukan riwayat billing (transaksi + invoice + simulasi pembayaran) satu pelanggan,
 * dipakai bersama oleh DemoDataSeeder (data kurasi) dan BulkCustomerSeeder (data massal).
 */
trait GeneratesCustomerBilling
{
    protected JournalPostingService $journal;

    protected InvoiceBuilder $invoiceBuilder;

    protected PaymentService $paymentService;

    protected TransactionService $transactionService;

    protected Generator $faker;

    protected function initGenerators(): void
    {
        $this->journal = app(JournalPostingService::class);
        $this->invoiceBuilder = app(InvoiceBuilder::class);
        $this->paymentService = app(PaymentService::class);
        $this->transactionService = app(TransactionService::class);
        $this->faker = FakerFactory::create('id_ID');
    }

    /**
     * Buat transaksi bulanan (+ instalasi/perangkat di bulan pertama) sejak aktivasi sampai hari ini,
     * bentuk invoice per bulan, dan simulasikan pembayarannya.
     */
    private function billCustomerHistory(array $entry, Carbon $today, Product $installationProduct, Discount $prorateDiscount, \Illuminate\Support\Collection $manualDiscounts): void
    {
        $customer = $entry['customer'];
        $activation = Carbon::parse($customer->activation_date);
        $cursor = $activation->copy();
        $installationBilled = false;

        while ($cursor->lessThanOrEqualTo($today)) {
            $periodStart = $cursor->isSameDay($activation) ? $activation->copy() : $cursor->copy()->startOfMonth();
            $periodEnd = $cursor->copy()->endOfMonth()->lessThanOrEqualTo($today) ? $cursor->copy()->endOfMonth() : $today->copy();

            $isFirstMonth = $periodStart->isSameDay($activation) && $activation->day > 1;
            $transactions = collect();

            $discountId = null;
            if ($isFirstMonth) {
                $discountId = $prorateDiscount->id;
            } elseif (random_int(0, 100) < 15) {
                $discountId = $manualDiscounts->random()->id;
            }

            $transactions->push($this->transactionService->create([
                'customer_id' => $customer->id,
                'product_id' => $entry['product']->id,
                'discount_id' => $discountId,
                'transaction_date' => $periodStart->toDateString(),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'qty' => 1,
                'activation_date' => $activation->toDateString(),
            ]));

            if (! $installationBilled) {
                $transactions->push($this->transactionService->create([
                    'customer_id' => $customer->id,
                    'product_id' => $installationProduct->id,
                    'transaction_date' => $activation->toDateString(),
                    'qty' => 1,
                ]));

                if ($entry['hardware']) {
                    $transactions->push($this->transactionService->create([
                        'customer_id' => $customer->id,
                        'product_id' => $entry['hardware']->id,
                        'transaction_date' => $activation->toDateString(),
                        'qty' => 1,
                    ]));
                }
                $installationBilled = true;
            }

            $invoice = $this->invoiceBuilder->build($transactions, $periodStart->toDateString(), 7);
            $this->simulatePayments($invoice, $today);

            $cursor = $cursor->copy()->addMonthNoOverflow()->startOfMonth();
        }
    }

    /**
     * Simulasikan hasil penagihan invoice: lunas penuh, dicicil, atau (sebagian) belum dibayar,
     * dengan kecenderungan lebih banyak nunggak untuk invoice yang sudah lewat jatuh tempo lama.
     */
    private function simulatePayments(Invoice $invoice, Carbon $today): void
    {
        $daysPastDue = $today->diffInDays($invoice->due_date, false) * -1;
        $roll = random_int(1, 100);

        if ($daysPastDue > 30) {
            $bucket = $roll <= 90 ? 'lunas' : ($roll <= 96 ? 'cicilan' : 'belum_lunas');
        } else {
            $bucket = $roll <= 70 ? 'lunas' : ($roll <= 92 ? 'cicilan' : 'belum_lunas');
        }

        $collectorId = $invoice->collector_id;

        if ($bucket === 'lunas') {
            $payDate = Carbon::parse($invoice->invoice_date)->addDays(random_int(0, 10))->min($today);
            $this->paymentService->record($invoice, [
                'amount' => $invoice->grand_total,
                'payment_date' => $payDate->toDateString(),
                'method' => $this->faker->randomElement(['transfer', 'cash', 'ewallet']),
                'status' => 'confirmed',
                'collector_id' => $collectorId,
                'confirmed_by' => $collectorId,
            ]);
        } elseif ($bucket === 'cicilan') {
            $portion = random_int(30, 70) / 100;
            $firstAmount = round((float) $invoice->grand_total * $portion, 2);
            $payDate = Carbon::parse($invoice->invoice_date)->addDays(random_int(0, 10))->min($today);
            $this->paymentService->record($invoice, [
                'amount' => $firstAmount,
                'payment_date' => $payDate->toDateString(),
                'method' => 'transfer',
                'status' => 'confirmed',
                'collector_id' => $collectorId,
                'confirmed_by' => $collectorId,
            ]);
        } elseif (random_int(0, 100) < 25) {
            $payDate = Carbon::parse($invoice->invoice_date)->addDays(random_int(0, 5))->min($today);
            if ($payDate->lessThanOrEqualTo($today)) {
                $this->paymentService->record($invoice, [
                    'amount' => round((float) $invoice->grand_total * 0.5, 2),
                    'payment_date' => $payDate->toDateString(),
                    'method' => 'transfer',
                    'status' => 'pending',
                    'collector_id' => $collectorId,
                ]);
            }
        }

        $invoice->refreshStatus();
    }

    private function randomPhone(): string
    {
        return '08'.random_int(11, 29).random_int(10000000, 99999999);
    }

    private function weightedIndex(array $weights, int $count): int
    {
        $weights = array_slice($weights, 0, $count);
        $total = array_sum($weights);
        $roll = random_int(1, $total);
        $cumulative = 0;
        foreach ($weights as $i => $w) {
            $cumulative += $w;
            if ($roll <= $cumulative) {
                return $i;
            }
        }

        return $count - 1;
    }
}
