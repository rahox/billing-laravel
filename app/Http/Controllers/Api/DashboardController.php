<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Reports\BillingReportService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private BillingReportService $reports) {}

    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('super-admin')) {
            return response()->json([
                'role' => 'super-admin',
                'summary' => $this->reports->dashboardSummary($request->query('from'), $request->query('to')),
            ]);
        }

        if ($user->hasRole('reseller')) {
            $report = $this->reports->transactionReport($user->id, $request->query('from'), $request->query('to'));

            return response()->json(['role' => 'reseller', 'summary' => $report['summary']]);
        }

        if ($user->hasRole('sales')) {
            $report = $this->reports->salesReport($user->id, $request->query('from'), $request->query('to'));

            return response()->json(['role' => 'sales', 'summary' => $report['summary']]);
        }

        if ($user->hasRole('collector')) {
            $report = $this->reports->collectorReport($user->id, $request->query('from'), $request->query('to'));

            return response()->json(['role' => 'collector', 'summary' => $report['summary']]);
        }

        return response()->json(['role' => null, 'summary' => []]);
    }
}
