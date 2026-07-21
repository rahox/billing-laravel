<?php

namespace App\Services\Billing;

use App\Models\Commission;
use App\Models\Payment;
use App\Services\Accounting\JournalPostingService;

class CommissionService
{
    public function __construct(private JournalPostingService $journal) {}

    /**
     * Hitung & akrual komisi sales saat sebuah pembayaran pelanggan dikonfirmasi.
     * Tipe flat diakru sekali per invoice (saat pembayaran pertama masuk),
     * tipe percentage diakru proporsional dari setiap nominal pembayaran yang diterima.
     */
    public function accrueForPayment(Payment $payment): ?Commission
    {
        $invoice = $payment->invoice;
        $sales = $invoice?->sales;

        if (! $sales || ! $sales->commission_type || (float) $sales->commission_value <= 0) {
            return null;
        }

        if ($sales->commission_type === 'flat') {
            $alreadyAccrued = Commission::where('invoice_id', $invoice->id)
                ->where('commission_type', 'flat')
                ->exists();

            if ($alreadyAccrued) {
                return null;
            }

            $amount = (float) $sales->commission_value;
        } else {
            $amount = round((float) $payment->amount * ((float) $sales->commission_value / 100), 2);
        }

        if ($amount <= 0) {
            return null;
        }

        $commission = Commission::create([
            'sales_id' => $sales->id,
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'base_amount' => $payment->amount,
            'commission_type' => $sales->commission_type,
            'commission_value' => $sales->commission_value,
            'commission_amount' => $amount,
            'status' => 'pending',
            'earned_date' => $payment->payment_date,
        ]);

        $this->journal->postCommissionAccrual($commission);

        return $commission;
    }
}
