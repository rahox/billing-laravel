<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Models\Commission;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class JournalPostingService
{
    /**
     * Jurnal pengakuan pendapatan saat invoice diterbitkan (accrual basis):
     * Dr Piutang Usaha, Dr Diskon Penjualan, Dr Beban BHP, Dr Beban USO
     *     Cr Pendapatan (per kategori produk), Cr PPN Keluaran, Cr Utang BHP, Cr Utang USO
     * Ditambah pengakuan HPP untuk baris barang: Dr HPP Perangkat / Cr Persediaan Perangkat.
     */
    public function postInvoice(Invoice $invoice): JournalEntry
    {
        return DB::transaction(function () use ($invoice) {
            $invoice->loadMissing('transactions.product');

            $lines = [];
            $revenueByAccount = [];
            $cogsTotal = 0.0;

            foreach ($invoice->transactions as $trx) {
                $revenueAccountCode = match ($trx->product->type) {
                    'barang' => config('billing.accounts.pendapatan_penjualan_perangkat'),
                    default => $trx->product->is_telco_levy_applicable
                        ? config('billing.accounts.pendapatan_jasa_internet')
                        : config('billing.accounts.pendapatan_jasa_lainnya'),
                };

                $gross = (float) $trx->unit_price * $trx->qty;
                $revenueByAccount[$revenueAccountCode] = ($revenueByAccount[$revenueAccountCode] ?? 0) + $gross;

                if ($trx->product->type === 'barang') {
                    $cogsTotal += (float) $trx->product->cost_price * $trx->qty;
                }
            }

            $lines[] = $this->debit('piutang_usaha', (float) $invoice->subtotal + (float) $invoice->ppn_total, "Piutang invoice {$invoice->invoice_number}");

            if ($invoice->discount_total > 0) {
                $lines[] = $this->debit('diskon_penjualan', (float) $invoice->discount_total, "Diskon invoice {$invoice->invoice_number}");
            }
            if ($invoice->bhp_total > 0) {
                $lines[] = $this->debit('beban_bhp', (float) $invoice->bhp_total, "BHP invoice {$invoice->invoice_number}");
            }
            if ($invoice->uso_total > 0) {
                $lines[] = $this->debit('beban_uso', (float) $invoice->uso_total, "USO invoice {$invoice->invoice_number}");
            }

            foreach ($revenueByAccount as $code => $amount) {
                $lines[] = $this->creditByCode($code, $amount, "Pendapatan invoice {$invoice->invoice_number}");
            }

            $lines[] = $this->credit('ppn_keluaran', (float) $invoice->ppn_total, "PPN Keluaran invoice {$invoice->invoice_number}");

            if ($invoice->bhp_total > 0) {
                $lines[] = $this->credit('utang_bhp', (float) $invoice->bhp_total, "Utang BHP invoice {$invoice->invoice_number}");
            }
            if ($invoice->uso_total > 0) {
                $lines[] = $this->credit('utang_uso', (float) $invoice->uso_total, "Utang USO invoice {$invoice->invoice_number}");
            }

            if ($cogsTotal > 0) {
                $lines[] = $this->debit('hpp_perangkat', $cogsTotal, "HPP invoice {$invoice->invoice_number}");
                $lines[] = $this->credit('persediaan_perangkat', $cogsTotal, "Pengurangan persediaan invoice {$invoice->invoice_number}");
            }

            return $this->createEntry($invoice->invoice_date, "Invoice {$invoice->invoice_number} - {$invoice->customer?->name}", 'invoice', $invoice->id, $lines);
        });
    }

    /**
     * Jurnal penerimaan pembayaran terkonfirmasi: Dr Kas/Bank, Cr Piutang Usaha.
     */
    public function postPayment(Payment $payment): JournalEntry
    {
        return DB::transaction(function () use ($payment) {
            $cashAccount = $payment->method === 'cash' ? 'kas' : 'bank';

            $lines = [
                $this->debit($cashAccount, (float) $payment->amount, "Pembayaran {$payment->payment_number}"),
                $this->credit('piutang_usaha', (float) $payment->amount, "Pelunasan invoice {$payment->invoice?->invoice_number}"),
            ];

            return $this->createEntry($payment->payment_date, "Pembayaran {$payment->payment_number} - invoice {$payment->invoice?->invoice_number}", 'payment', $payment->id, $lines);
        });
    }

    /**
     * Jurnal beban operasional/pembelian: Dr Beban/Aset sesuai kategori, Cr Kas/Bank.
     */
    public function postExpense(Expense $expense): JournalEntry
    {
        return DB::transaction(function () use ($expense) {
            $accountCode = $expense->account?->code
                ?? config("billing.accounts.expense_category_map.{$expense->category}")
                ?? config('billing.accounts.expense_category_map.lainnya');

            $lines = [
                $this->debitByCode($accountCode, (float) $expense->amount, $expense->description),
                $this->credit('bank', (float) $expense->amount, "Pembayaran {$expense->expense_number}"),
            ];

            return $this->createEntry($expense->expense_date, "Beban {$expense->expense_number} - {$expense->description}", 'expense', $expense->id, $lines);
        });
    }

    /**
     * Jurnal akrual komisi sales saat pembayaran pelanggan dikonfirmasi:
     * Dr Beban Komisi Sales, Cr Komisi Sales Terutang.
     */
    public function postCommissionAccrual(Commission $commission): JournalEntry
    {
        return DB::transaction(function () use ($commission) {
            $lines = [
                $this->debit('beban_komisi', (float) $commission->commission_amount, "Komisi {$commission->sales?->name} - invoice {$commission->invoice?->invoice_number}"),
                $this->credit('komisi_terutang', (float) $commission->commission_amount, "Akrual komisi {$commission->sales?->name}"),
            ];

            return $this->createEntry($commission->earned_date, "Akrual komisi {$commission->sales?->name} - invoice {$commission->invoice?->invoice_number}", 'commission', $commission->id, $lines);
        });
    }

    /**
     * Jurnal pembayaran komisi ke sales: Dr Komisi Sales Terutang, Cr Kas/Bank.
     */
    public function postCommissionPayment(Commission $commission): JournalEntry
    {
        return DB::transaction(function () use ($commission) {
            $lines = [
                $this->debit('komisi_terutang', (float) $commission->commission_amount, "Pembayaran komisi {$commission->sales?->name}"),
                $this->credit('bank', (float) $commission->commission_amount, "Pembayaran komisi {$commission->sales?->name}"),
            ];

            return $this->createEntry(now(), "Pembayaran komisi {$commission->sales?->name}", 'commission_payment', $commission->id, $lines);
        });
    }

    /**
     * Jurnal setoran modal pemilik: Dr Bank, Cr Modal Pemilik.
     */
    public function postCapitalInjection(\DateTimeInterface|string $date, float $amount, string $description = 'Setoran modal awal pemilik', int $referenceId = 0): JournalEntry
    {
        $lines = [
            $this->debit('bank', $amount, $description),
            $this->credit('modal_pemilik', $amount, $description),
        ];

        return $this->createEntry($date, $description, 'capital', $referenceId, $lines);
    }

    private function debit(string $accountKey, float $amount, ?string $memo = null): array
    {
        return $this->debitByCode(config("billing.accounts.{$accountKey}"), $amount, $memo);
    }

    private function credit(string $accountKey, float $amount, ?string $memo = null): array
    {
        return $this->creditByCode(config("billing.accounts.{$accountKey}"), $amount, $memo);
    }

    private function debitByCode(string $accountCode, float $amount, ?string $memo = null): array
    {
        return ['account_id' => $this->accountId($accountCode), 'debit' => $amount, 'credit' => 0, 'memo' => $memo];
    }

    private function creditByCode(string $accountCode, float $amount, ?string $memo = null): array
    {
        return ['account_id' => $this->accountId($accountCode), 'debit' => 0, 'credit' => $amount, 'memo' => $memo];
    }

    private function accountId(string $code): int
    {
        return ChartOfAccount::where('code', $code)->value('id')
            ?? throw new \RuntimeException("Chart of account {$code} tidak ditemukan");
    }

    private function createEntry(\DateTimeInterface|string $date, string $description, string $referenceType, int $referenceId, array $lines): JournalEntry
    {
        $entry = JournalEntry::create([
            'journal_number' => 'JRN-TEMP',
            'entry_date' => $date,
            'description' => $description,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);

        $entry->update(['journal_number' => 'JRN-'.str_pad((string) $entry->id, 7, '0', STR_PAD_LEFT)]);

        foreach ($lines as $line) {
            $entry->lines()->create($line);
        }

        return $entry;
    }
}
