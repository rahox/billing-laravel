<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private JournalPostingService $journal,
        private CommissionService $commission,
    ) {}

    /**
     * Catat pembayaran baru. Mendukung cicilan (nominal boleh kurang dari sisa tagihan).
     */
    public function record(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data) {
            $payment = Payment::create([
                'payment_number' => 'PAY-TEMP',
                'invoice_id' => $invoice->id,
                'amount' => $data['amount'],
                'payment_date' => $data['payment_date'],
                'method' => $data['method'] ?? 'transfer',
                'status' => $data['status'] ?? 'pending',
                'collector_id' => $data['collector_id'] ?? $invoice->collector_id,
                'notes' => $data['notes'] ?? null,
            ]);

            $payment->payment_number = 'PAY-'.str_pad((string) $payment->id, 7, '0', STR_PAD_LEFT);
            $payment->save();

            if (($data['status'] ?? 'pending') === 'confirmed') {
                $this->confirm($payment, $data['confirmed_by'] ?? null);
            }

            return $payment->fresh();
        });
    }

    /**
     * Konfirmasi pembayaran diterima: posting jurnal kas masuk, update status invoice
     * (lunas/cicilan/belum lunas), dan akrual komisi sales bila berlaku.
     */
    public function confirm(Payment $payment, ?int $confirmedBy = null): Payment
    {
        return DB::transaction(function () use ($payment, $confirmedBy) {
            if ($payment->status !== 'confirmed') {
                $payment->status = 'confirmed';
                $payment->confirmed_by = $confirmedBy;
                $payment->confirmed_at = now();
                $payment->save();

                $this->journal->postPayment($payment);
            }

            $payment->invoice->refreshStatus();
            $this->commission->accrueForPayment($payment);

            return $payment->fresh();
        });
    }
}
