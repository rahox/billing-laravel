<?php

namespace App\Services\Billing;

use App\Models\Discount;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class TransactionService
{
    public function __construct(private PricingService $pricing) {}

    /**
     * Buat satu baris transaksi (item invoice) untuk pelanggan: hitung harga, diskon, PPN/BHP/USO.
     */
    public function create(array $data): Transaction
    {
        $product = Product::findOrFail($data['product_id']);
        $discount = ! empty($data['discount_id']) ? Discount::find($data['discount_id']) : null;
        $qty = (int) ($data['qty'] ?? 1);

        $discountContext = [];
        if ($discount && $discount->mode === 'prorate_activation'
            && ! empty($data['period_start']) && ! empty($data['period_end']) && ! empty($data['activation_date'])) {
            $discountContext = $this->pricing->activationProrationContext(
                Carbon::parse($data['period_start']),
                Carbon::parse($data['period_end']),
                Carbon::parse($data['activation_date']),
            );
        }

        $calc = $this->pricing->calculateLine($product, $qty, $discount, $discountContext);

        $transaction = Transaction::create([
            'transaction_number' => 'TRX-TEMP',
            'customer_id' => $data['customer_id'],
            'product_id' => $product->id,
            'discount_id' => $discount?->id,
            'transaction_date' => $data['transaction_date'] ?? now(),
            'period_start' => $data['period_start'] ?? null,
            'period_end' => $data['period_end'] ?? null,
            ...$calc,
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        $transaction->transaction_number = 'TRX-'.Carbon::parse($transaction->transaction_date)->format('Ymd').'-'.str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT);
        $transaction->save();

        return $transaction;
    }
}
