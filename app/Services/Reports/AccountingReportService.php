<?php

namespace App\Services\Reports;

use App\Models\ChartOfAccount;
use App\Models\JournalEntryLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccountingReportService
{
    /**
     * Saldo tiap akun dalam rentang tanggal, mengikuti konvensi normal per tipe akun:
     * asset & expense = debit - credit, liability/equity/revenue = credit - debit.
     * Dengan konvensi ini, akun kontra (mis. Diskon Penjualan, Akumulasi Penyusutan) otomatis
     * mengurangi total kelompoknya karena polaritasnya berlawanan dari akun normal di tipe yang sama.
     */
    private function balancesByType(string $type, ?string $from, ?string $to): \Illuminate\Support\Collection
    {
        $query = ChartOfAccount::query()->where('type', $type)->orderBy('code');

        $sums = JournalEntryLine::query()
            ->select('account_id', DB::raw('SUM(debit) as total_debit'), DB::raw('SUM(credit) as total_credit'))
            ->whereHas('journalEntry', function ($q) use ($from, $to) {
                if ($from) {
                    $q->whereDate('entry_date', '>=', $from);
                }
                if ($to) {
                    $q->whereDate('entry_date', '<=', $to);
                }
            })
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');

        return $query->get()->map(function (ChartOfAccount $account) use ($sums, $type) {
            $sum = $sums->get($account->id);
            $debit = (float) ($sum->total_debit ?? 0);
            $credit = (float) ($sum->total_credit ?? 0);
            $balance = in_array($type, ['asset', 'expense'])
                ? $debit - $credit
                : $credit - $debit;

            return [
                'code' => $account->code,
                'name' => $account->name,
                'balance' => round($balance, 2),
            ];
        })->filter(fn ($row) => abs($row['balance']) > 0.004)->values();
    }

    /**
     * Laporan Laba Rugi periode [from, to].
     */
    public function incomeStatement(string $from, string $to): array
    {
        $revenue = $this->balancesByType('revenue', $from, $to);
        $allExpense = $this->balancesByType('expense', $from, $to);

        $cogs = $allExpense->filter(fn ($row) => str_starts_with($row['code'], '5-10'));
        $opex = $allExpense->reject(fn ($row) => str_starts_with($row['code'], '5-10'));

        $totalRevenue = round($revenue->sum('balance'), 2);
        $totalCogs = round($cogs->sum('balance'), 2);
        $grossProfit = round($totalRevenue - $totalCogs, 2);
        $totalOpex = round($opex->sum('balance'), 2);
        $netIncome = round($grossProfit - $totalOpex, 2);

        return [
            'period' => ['from' => $from, 'to' => $to],
            'revenue' => $revenue,
            'total_revenue' => $totalRevenue,
            'cogs' => $cogs,
            'total_cogs' => $totalCogs,
            'gross_profit' => $grossProfit,
            'opex' => $opex,
            'total_opex' => $totalOpex,
            'net_income' => $netIncome,
        ];
    }

    /**
     * Neraca (Balance Sheet) per tanggal tertentu (kumulatif sejak awal pembukuan).
     */
    public function balanceSheet(string $asOfDate, string $inceptionDate = '2000-01-01'): array
    {
        $assets = $this->balancesByType('asset', $inceptionDate, $asOfDate);
        $liabilities = $this->balancesByType('liability', $inceptionDate, $asOfDate);
        $equity = $this->balancesByType('equity', $inceptionDate, $asOfDate);

        // Laba tahun berjalan yang belum ditutup ke Laba Ditahan, agar neraca tetap balance.
        $yearStart = Carbon::parse($asOfDate)->startOfYear()->toDateString();
        $currentYearIncome = $this->incomeStatement($inceptionDate, $asOfDate)['net_income']
            - $this->incomeStatement($inceptionDate, Carbon::parse($yearStart)->subDay()->toDateString())['net_income'];

        $equity = $equity->push([
            'code' => '3-9000',
            'name' => 'Laba Tahun Berjalan',
            'balance' => round($currentYearIncome, 2),
        ]);

        $totalAssets = round($assets->sum('balance'), 2);
        $totalLiabilities = round($liabilities->sum('balance'), 2);
        $totalEquity = round($equity->sum('balance'), 2);

        return [
            'as_of' => $asOfDate,
            'assets' => $assets,
            'total_assets' => $totalAssets,
            'liabilities' => $liabilities,
            'total_liabilities' => $totalLiabilities,
            'equity' => $equity,
            'total_equity' => $totalEquity,
            'total_liabilities_and_equity' => round($totalLiabilities + $totalEquity, 2),
            'is_balanced' => abs($totalAssets - ($totalLiabilities + $totalEquity)) < 1,
        ];
    }

    /**
     * Ringkasan pendapatan & beban untuk dashboard owner: total periode berjalan,
     * tren bulanan, dan komposisi pendapatan/beban per akun untuk grafik.
     */
    public function dashboardFinancials(?string $from = null, ?string $to = null): array
    {
        $to = $to ? Carbon::parse($to) : now();
        $from = $from ? Carbon::parse($from) : $to->copy()->subMonths(5)->startOfMonth();

        $period = $this->incomeStatement($from->toDateString(), $to->toDateString());

        $trend = collect();
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $monthEnd = $cursor->copy()->endOfMonth();
            if ($monthEnd->gt($to)) {
                $monthEnd = $to->copy();
            }

            $monthReport = $this->incomeStatement($cursor->toDateString(), $monthEnd->toDateString());
            $trend->push([
                'month' => $cursor->translatedFormat('M Y'),
                'pendapatan' => $monthReport['total_revenue'],
                'beban' => round($monthReport['total_cogs'] + $monthReport['total_opex'], 2),
                'laba' => $monthReport['net_income'],
            ]);

            $cursor->addMonthNoOverflow();
        }

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'total_pendapatan' => $period['total_revenue'],
            'total_beban' => round($period['total_cogs'] + $period['total_opex'], 2),
            'laba_bersih' => $period['net_income'],
            'pendapatan_by_category' => $period['revenue']
                ->filter(fn ($row) => $row['balance'] > 0)
                ->map(fn ($row) => ['name' => $row['name'], 'value' => $row['balance']])
                ->sortByDesc('value')
                ->values(),
            'beban_by_category' => $period['cogs']->concat($period['opex'])
                ->map(fn ($row) => ['name' => $row['name'], 'value' => $row['balance']])
                ->sortByDesc('value')
                ->values(),
            'monthly_trend' => $trend->values(),
        ];
    }
}
