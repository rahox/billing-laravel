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
     *
     * Ringkasan dihitung via agregasi SQL (bukan load semua baris ke PHP) supaya tetap
     * cepat berapa pun jumlah invoice-nya; daftar invoice mentah di-paginate.
     */
    public function transactionReport(?int $resellerId, ?string $from, ?string $to, int $perPage = 20): array
    {
        $base = Invoice::query();

        if ($resellerId) {
            $base->where('reseller_id', $resellerId);
        }
        if ($from) {
            $base->whereDate('invoice_date', '>=', $from);
        }
        if ($to) {
            $base->whereDate('invoice_date', '<=', $to);
        }

        $summary = (clone $base)->selectRaw("
            COUNT(*) as jumlah_invoice,
            COALESCE(SUM(grand_total), 0) as total_tagihan,
            COALESCE(SUM(paid_amount), 0) as total_terbayar,
            COALESCE(SUM(grand_total - paid_amount), 0) as total_piutang,
            SUM(CASE WHEN status = 'lunas' THEN 1 ELSE 0 END) as lunas,
            SUM(CASE WHEN status = 'cicilan' THEN 1 ELSE 0 END) as cicilan,
            SUM(CASE WHEN status IN ('belum_lunas', 'overdue') THEN 1 ELSE 0 END) as belum_lunas
        ")->first();

        $invoices = (clone $base)->with(['customer', 'reseller', 'sales', 'collector'])
            ->orderByDesc('invoice_date')
            ->paginate($perPage);

        return [
            'invoices' => $invoices,
            'summary' => [
                'jumlah_invoice' => (int) $summary->jumlah_invoice,
                'total_tagihan' => round((float) $summary->total_tagihan, 2),
                'total_terbayar' => round((float) $summary->total_terbayar, 2),
                'total_piutang' => round((float) $summary->total_piutang, 2),
                'lunas' => (int) $summary->lunas,
                'cicilan' => (int) $summary->cicilan,
                'belum_lunas' => (int) $summary->belum_lunas,
            ],
        ];
    }

    /**
     * Laporan komisi & performa sales.
     */
    public function salesReport(int $salesId, ?string $from, ?string $to, int $perPage = 20): array
    {
        $base = Commission::query()->where('sales_id', $salesId);

        if ($from) {
            $base->whereDate('earned_date', '>=', $from);
        }
        if ($to) {
            $base->whereDate('earned_date', '<=', $to);
        }

        $summary = (clone $base)->selectRaw("
            COUNT(*) as jumlah_transaksi_komisi,
            COALESCE(SUM(commission_amount), 0) as total_komisi,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN commission_amount ELSE 0 END), 0) as komisi_pending,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN commission_amount ELSE 0 END), 0) as komisi_paid
        ")->first();

        $commissions = (clone $base)->with(['invoice.customer'])
            ->orderByDesc('earned_date')
            ->paginate($perPage);

        return [
            'commissions' => $commissions,
            'summary' => [
                'total_komisi' => round((float) $summary->total_komisi, 2),
                'komisi_pending' => round((float) $summary->komisi_pending, 2),
                'komisi_paid' => round((float) $summary->komisi_paid, 2),
                'jumlah_transaksi_komisi' => (int) $summary->jumlah_transaksi_komisi,
            ],
        ];
    }

    /**
     * Laporan penagihan untuk collector: invoice yang perlu ditagih + riwayat konfirmasi pembayaran.
     */
    public function collectorReport(int $collectorId, ?string $from, ?string $to, int $perPage = 20): array
    {
        $outstandingBase = Invoice::query()
            ->where('collector_id', $collectorId)
            ->whereIn('status', ['belum_lunas', 'cicilan', 'overdue']);

        $outstandingSummary = (clone $outstandingBase)->selectRaw("
            COUNT(*) as jumlah_perlu_ditagih,
            COALESCE(SUM(grand_total - paid_amount), 0) as total_perlu_ditagih
        ")->first();

        $outstanding = (clone $outstandingBase)->with('customer')
            ->orderBy('due_date')
            ->paginate($perPage, ['*'], 'ditagih_page');

        $paymentsBase = Payment::query()->where('collector_id', $collectorId);
        if ($from) {
            $paymentsBase->whereDate('payment_date', '>=', $from);
        }
        if ($to) {
            $paymentsBase->whereDate('payment_date', '<=', $to);
        }

        $paymentsSummary = (clone $paymentsBase)->selectRaw("
            COALESCE(SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END), 0) as total_berhasil_ditagih,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as jumlah_konfirmasi
        ")->first();

        $payments = (clone $paymentsBase)->with('invoice.customer')
            ->orderByDesc('payment_date')
            ->paginate($perPage, ['*'], 'riwayat_page');

        return [
            'perlu_ditagih' => $outstanding,
            'riwayat_pembayaran' => $payments,
            'summary' => [
                'jumlah_perlu_ditagih' => (int) $outstandingSummary->jumlah_perlu_ditagih,
                'total_perlu_ditagih' => round((float) $outstandingSummary->total_perlu_ditagih, 2),
                'total_berhasil_ditagih' => round((float) $paymentsSummary->total_berhasil_ditagih, 2),
                'jumlah_konfirmasi' => (int) $paymentsSummary->jumlah_konfirmasi,
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

        $summary = $query->selectRaw("
            COALESCE(SUM(grand_total), 0) as total_omzet,
            COALESCE(SUM(paid_amount), 0) as total_terbayar,
            COALESCE(SUM(grand_total - paid_amount), 0) as total_piutang,
            SUM(CASE WHEN status = 'lunas' THEN 1 ELSE 0 END) as invoice_lunas,
            SUM(CASE WHEN status = 'cicilan' THEN 1 ELSE 0 END) as invoice_cicilan,
            SUM(CASE WHEN status IN ('belum_lunas', 'overdue') THEN 1 ELSE 0 END) as invoice_belum_lunas
        ")->first();

        return [
            'total_omzet' => round((float) $summary->total_omzet, 2),
            'total_terbayar' => round((float) $summary->total_terbayar, 2),
            'total_piutang' => round((float) $summary->total_piutang, 2),
            'jumlah_pelanggan_aktif' => DB::table('customers')->where('status', 'active')->count(),
            'invoice_lunas' => (int) $summary->invoice_lunas,
            'invoice_cicilan' => (int) $summary->invoice_cicilan,
            'invoice_belum_lunas' => (int) $summary->invoice_belum_lunas,
        ];
    }
}
