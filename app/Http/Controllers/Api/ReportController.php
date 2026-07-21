<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Reports\AccountingReportService;
use App\Services\Reports\BillingReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function __construct(
        private BillingReportService $billingReport,
        private AccountingReportService $accountingReport,
    ) {}

    /**
     * Laporan transaksi: Owner melihat semua/opsional filter reseller, Reseller dibatasi ke dirinya.
     */
    public function transactions(Request $request)
    {
        $user = $request->user();
        $resellerId = null;

        if ($user->hasRole('reseller')) {
            $resellerId = $user->id;
        } elseif ($user->hasRole('super-admin')) {
            $resellerId = $request->query('reseller_id');
        } else {
            abort(403, 'Laporan ini hanya untuk Owner/Reseller.');
        }

        return response()->json($this->billingReport->transactionReport($resellerId, $request->query('from'), $request->query('to')));
    }

    /**
     * Laporan komisi sales. Sales hanya melihat dirinya sendiri; super-admin bisa pilih sales_id.
     */
    public function sales(Request $request)
    {
        $user = $request->user();
        if ($user->hasRole('sales')) {
            $salesId = $user->id;
        } elseif ($user->hasRole('super-admin') && $request->query('sales_id')) {
            $salesId = (int) $request->query('sales_id');
        } else {
            abort(403, 'Laporan ini hanya untuk Sales/Owner (dengan sales_id).');
        }

        return response()->json($this->billingReport->salesReport($salesId, $request->query('from'), $request->query('to')));
    }

    /**
     * Laporan penagihan collector. Collector hanya dirinya; super-admin bisa pilih collector_id.
     */
    public function collector(Request $request)
    {
        $user = $request->user();
        if ($user->hasRole('collector')) {
            $collectorId = $user->id;
        } elseif ($user->hasRole('super-admin') && $request->query('collector_id')) {
            $collectorId = (int) $request->query('collector_id');
        } else {
            abort(403, 'Laporan ini hanya untuk Collector/Owner (dengan collector_id).');
        }

        return response()->json($this->billingReport->collectorReport($collectorId, $request->query('from'), $request->query('to')));
    }

    /**
     * Laporan Laba Rugi - khusus Owner (super-admin).
     */
    public function incomeStatement(Request $request)
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);

        $from = $request->query('from', Carbon::now()->startOfYear()->toDateString());
        $to = $request->query('to', Carbon::now()->toDateString());

        return response()->json($this->accountingReport->incomeStatement($from, $to));
    }

    /**
     * Neraca (Balance Sheet) - khusus Owner (super-admin).
     */
    public function balanceSheet(Request $request)
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);

        $asOf = $request->query('as_of', Carbon::now()->toDateString());

        return response()->json($this->accountingReport->balanceSheet($asOf));
    }
}
