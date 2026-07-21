<?php

namespace App\Services\Reports;

use App\Models\Commission;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class BillingReportService
{
    /**
     * Laporan transaksi/invoice untuk Owner (super-admin) & Reseller.
     * Reseller otomatis dibatasi ke resellerId miliknya sendiri di layer controller.
     */
    public function transactionReport(?int $resellerId, ?string $from, ?string $to): array
    {
        $query = Invoice::query()->with(['customer', 'reseller', 'sales', 'collector']);

        if ($resellerId) {
            $query->where('reseller_id', $resellerId);
        }
        if ($from) {
            $query->whereDate('invoice_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('invoice_date', '<=', $to);
        }

        $invoices = $query->orderByDesc('invoice_date')->get();

        return [
            'invoices' => $invoices,
            'summary' => [
                'jumlah_invoice' => $invoices->count(),
                'total_tagihan' => round($invoices->sum('grand_total'), 2),
                'total_terbayar' => round($invoices->sum('paid_amount'), 2),
                'total_piutang' => round($invoices->sum(fn ($i) => $i->grand_total - $i->paid_amount), 2),
                'lunas' => $invoices->where('status', 'lunas')->count(),
                'cicilan' => $invoices->where('status', 'cicilan')->count(),
                'belum_lunas' => $invoices->whereIn('status', ['belum_lunas', 'overdue'])->count(),
            ],
        ];
    }

    /**
     * Laporan komisi & performa sales.
     */
    public function salesReport(int $salesId, ?string $from, ?string $to): array
    {
        $query = Commission::query()->with(['invoice.customer'])->where('sales_id', $salesId);

        if ($from) {
            $query->whereDate('earned_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('earned_date', '<=', $to);
        }

        $commissions = $query->orderByDesc('earned_date')->get();

        return [
            'commissions' => $commissions,
            'summary' => [
                'total_komisi' => round($commissions->sum('commission_amount'), 2),
                'komisi_pending' => round($commissions->where('status', 'pending')->sum('commission_amount'), 2),
                'komisi_paid' => round($commissions->where('status', 'paid')->sum('commission_amount'), 2),
                'jumlah_transaksi_komisi' => $commissions->count(),
            ],
        ];
    }

    /**
     * Laporan penagihan untuk collector: invoice yang perlu ditagih + riwayat konfirmasi pembayaran.
     */
    public function collectorReport(int $collectorId, ?string $from, ?string $to): array
    {
        $outstanding = Invoice::query()
            ->with('customer')
            ->where('collector_id', $collectorId)
            ->whereIn('status', ['belum_lunas', 'cicilan', 'overdue'])
            ->orderBy('due_date')
            ->get();

        $paymentsQuery = Payment::query()->with('invoice.customer')->where('collector_id', $collectorId);
        if ($from) {
            $paymentsQuery->whereDate('payment_date', '>=', $from);
        }
        if ($to) {
            $paymentsQuery->whereDate('payment_date', '<=', $to);
        }
        $payments = $paymentsQuery->orderByDesc('payment_date')->get();

        return [
            'perlu_ditagih' => $outstanding,
            'riwayat_pembayaran' => $payments,
            'summary' => [
                'jumlah_perlu_ditagih' => $outstanding->count(),
                'total_perlu_ditagih' => round($outstanding->sum(fn ($i) => $i->grand_total - $i->paid_amount), 2),
                'total_berhasil_ditagih' => round($payments->where('status', 'confirmed')->sum('amount'), 2),
                'jumlah_konfirmasi' => $payments->where('status', 'confirmed')->count(),
            ],
        ];
    }

    /**
     * Ringkasan dashboard umum (dipakai Owner): omzet, piutang, invoice per status.
     */
    public function dashboardSummary(?string $from, ?string $to): array
    {
        $query = Invoice::query();
        if ($from) {
            $query->whereDate('invoice_date', '>=', $from);
        }
        if ($to) {
            $query->whereDate('invoice_date', '<=', $to);
        }
        $invoices = $query->get();

        return [
            'total_omzet' => round($invoices->sum('grand_total'), 2),
            'total_terbayar' => round($invoices->sum('paid_amount'), 2),
            'total_piutang' => round($invoices->sum(fn ($i) => $i->grand_total - $i->paid_amount), 2),
            'jumlah_pelanggan_aktif' => DB::table('customers')->where('status', 'active')->count(),
            'invoice_lunas' => $invoices->where('status', 'lunas')->count(),
            'invoice_cicilan' => $invoices->where('status', 'cicilan')->count(),
            'invoice_belum_lunas' => $invoices->whereIn('status', ['belum_lunas', 'overdue'])->count(),
        ];
    }
}
