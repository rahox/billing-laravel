<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InvoiceBuilder
{
    public function __construct(private JournalPostingService $journal) {}

    /**
     * Bentuk satu invoice dari beberapa transaksi draft milik pelanggan yang sama.
     *
     * @param  Collection<int, Transaction>  $transactions
     */
    public function build(Collection $transactions, ?string $invoiceDate = null, int $dueDays = 14, ?int $createdBy = null): Invoice
    {
        if ($transactions->isEmpty()) {
            throw new \InvalidArgumentException('Minimal 1 transaksi diperlukan untuk membentuk invoice.');
        }

        $customerIds = $transactions->pluck('customer_id')->unique();
        if ($customerIds->count() > 1) {
            throw new \InvalidArgumentException('Semua transaksi dalam satu invoice harus milik pelanggan yang sama.');
        }

        $invalidStatus = $transactions->firstWhere('status', '!=', 'draft');
        if ($invalidStatus) {
            throw new \InvalidArgumentException('Hanya transaksi berstatus draft yang bisa diinvoice.');
        }

        $customer = $transactions->first()->customer;
        $invoiceDate = $invoiceDate ? Carbon::parse($invoiceDate) : now();

        $invoice = Invoice::create([
            'invoice_number' => 'INV-TEMP',
            'customer_id' => $customer->id,
            'reseller_id' => $customer->reseller_id,
            'sales_id' => $customer->sales_id,
            'collector_id' => $customer->collector_id,
            'invoice_date' => $invoiceDate,
            'due_date' => $invoiceDate->copy()->addDays($dueDays),
            'subtotal' => $transactions->sum('subtotal'),
            'discount_total' => $transactions->sum('discount_amount'),
            'ppn_total' => $transactions->sum('ppn_amount'),
            'bhp_total' => $transactions->sum('bhp_amount'),
            'uso_total' => $transactions->sum('uso_amount'),
            'created_by' => $createdBy,
        ]);

        $invoice->grand_total = $invoice->subtotal + $invoice->ppn_total;
        $invoice->invoice_number = 'INV-'.$invoiceDate->format('Ym').'-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT);
        $invoice->save();

        Transaction::whereIn('id', $transactions->pluck('id'))
            ->update(['invoice_id' => $invoice->id, 'status' => 'invoiced']);

        $this->journal->postInvoice($invoice);

        return $invoice->fresh(['transactions', 'customer']);
    }
}
