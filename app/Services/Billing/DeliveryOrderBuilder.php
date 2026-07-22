<?php

namespace App\Services\Billing;

use App\Models\DeliveryOrder;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DeliveryOrderBuilder
{
    /**
     * Bentuk satu surat jalan (delivery order) dari beberapa transaksi barang milik pelanggan yang sama.
     *
     * @param  Collection<int, Transaction>  $transactions
     */
    public function build(Collection $transactions, array $data, ?int $createdBy = null): DeliveryOrder
    {
        if ($transactions->isEmpty()) {
            throw new \InvalidArgumentException('Minimal 1 transaksi diperlukan untuk membentuk delivery order.');
        }

        $customerIds = $transactions->pluck('customer_id')->unique();
        if ($customerIds->count() > 1) {
            throw new \InvalidArgumentException('Semua transaksi dalam satu delivery order harus milik pelanggan yang sama.');
        }

        $notBarang = $transactions->first(fn (Transaction $trx) => $trx->product->type !== 'barang');
        if ($notBarang) {
            throw new \InvalidArgumentException('Delivery order hanya bisa dibuat dari transaksi produk barang.');
        }

        $alreadyAssigned = $transactions->first(fn (Transaction $trx) => $trx->delivery_order_id !== null);
        if ($alreadyAssigned) {
            throw new \InvalidArgumentException('Salah satu transaksi sudah masuk delivery order lain.');
        }

        $voided = $transactions->firstWhere('status', 'void');
        if ($voided) {
            throw new \InvalidArgumentException('Transaksi berstatus void tidak bisa dikirim.');
        }

        $customer = $transactions->first()->customer;
        $deliveryDate = ! empty($data['delivery_date']) ? Carbon::parse($data['delivery_date']) : now();

        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-TEMP',
            'customer_id' => $customer->id,
            'reseller_id' => $customer->reseller_id,
            'sales_id' => $customer->sales_id,
            'collector_id' => $customer->collector_id,
            'delivery_date' => $deliveryDate,
            'recipient_name' => $data['recipient_name'] ?? $customer->name,
            'address' => $data['address'] ?? $customer->address,
            'courier' => $data['courier'] ?? null,
            'tracking_number' => $data['tracking_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $createdBy,
        ]);

        $deliveryOrder->do_number = 'DO-'.$deliveryDate->format('Ymd').'-'.str_pad((string) $deliveryOrder->id, 6, '0', STR_PAD_LEFT);
        $deliveryOrder->save();

        Transaction::whereIn('id', $transactions->pluck('id'))
            ->update(['delivery_order_id' => $deliveryOrder->id]);

        return $deliveryOrder->fresh(['transactions.product', 'customer']);
    }
}
