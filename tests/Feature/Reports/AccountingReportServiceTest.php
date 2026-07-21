<?php

namespace Tests\Feature\Reports;

use App\Services\Accounting\JournalPostingService;
use App\Services\Billing\InvoiceBuilder;
use App\Services\Billing\PaymentService;
use App\Services\Billing\TransactionService;
use App\Services\Reports\AccountingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBillingData;
use Tests\TestCase;

class AccountingReportServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBillingData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();
    }

    public function test_balance_sheet_stays_balanced_after_capital_invoice_and_payment(): void
    {
        app(JournalPostingService::class)->postCapitalInjection(now()->subDays(60), 10000000);

        $customer = $this->createCustomer();
        $product = $this->createInternetProduct(['price' => 200000]);
        $transaction = app(TransactionService::class)->create(['customer_id' => $customer->id, 'product_id' => $product->id]);
        $invoice = app(InvoiceBuilder::class)->build(collect([$transaction]));

        app(PaymentService::class)->record($invoice, [
            'amount' => (float) $invoice->grand_total,
            'payment_date' => now()->toDateString(),
            'status' => 'confirmed',
        ]);

        $report = app(AccountingReportService::class)->balanceSheet(now()->toDateString());

        $this->assertTrue($report['is_balanced'], 'Balance sheet assets should equal liabilities + equity.');
        $this->assertEqualsWithDelta($report['total_assets'], $report['total_liabilities_and_equity'], 0.01);
    }

    public function test_income_statement_reflects_revenue_and_net_income(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createInternetProduct(['price' => 200000]);
        $transaction = app(TransactionService::class)->create(['customer_id' => $customer->id, 'product_id' => $product->id]);
        app(InvoiceBuilder::class)->build(collect([$transaction]));

        $today = now()->toDateString();
        $report = app(AccountingReportService::class)->incomeStatement($today, $today);

        $this->assertSame(200000.0, $report['total_revenue']);
        // BHP (0.25%) + USO (1.25%) of the 200000 subtotal booked as opex.
        $this->assertSame(3000.0, $report['total_opex']);
        $this->assertSame(197000.0, $report['net_income']);
    }

    public function test_expense_reduces_net_income(): void
    {
        $account = \App\Models\ChartOfAccount::where('code', '5-2200')->firstOrFail();
        $expense = \App\Models\Expense::create([
            'expense_number' => 'EXP-0000001',
            'category' => 'gaji_karyawan',
            'description' => 'Gaji bulan berjalan',
            'amount' => 5000000,
            'expense_date' => now(),
            'account_id' => $account->id,
        ]);
        app(JournalPostingService::class)->postExpense($expense);

        $today = now()->toDateString();
        $report = app(AccountingReportService::class)->incomeStatement($today, $today);

        $this->assertSame(-5000000.0, $report['net_income']);
    }

    public function test_dashboard_financials_summarizes_revenue_expense_and_monthly_trend(): void
    {
        $customer = $this->createCustomer();
        $internet = $this->createInternetProduct(['price' => 200000]);
        $device = $this->createDeviceProduct(['price' => 350000, 'cost_price' => 250000]);

        $trxInternet = app(TransactionService::class)->create(['customer_id' => $customer->id, 'product_id' => $internet->id]);
        app(InvoiceBuilder::class)->build(collect([$trxInternet]));

        $trxDevice = app(TransactionService::class)->create(['customer_id' => $customer->id, 'product_id' => $device->id]);
        app(InvoiceBuilder::class)->build(collect([$trxDevice]));

        $today = now();
        $result = app(AccountingReportService::class)->dashboardFinancials($today->copy()->startOfMonth()->toDateString(), $today->toDateString());

        $this->assertSame(550000.0, $result['total_pendapatan']);
        $this->assertEqualsWithDelta($result['total_pendapatan'] - $result['total_beban'], $result['laba_bersih'], 0.01);

        $categories = collect($result['pendapatan_by_category'])->pluck('name');
        $this->assertTrue($categories->contains('Pendapatan Jasa Internet'));
        $this->assertTrue($categories->contains('Pendapatan Penjualan Perangkat'));
        $this->assertTrue(collect($result['pendapatan_by_category'])->every(fn ($row) => $row['value'] > 0));

        $this->assertNotEmpty($result['monthly_trend']);
        $currentMonth = collect($result['monthly_trend'])->last();
        $this->assertSame(550000.0, $currentMonth['pendapatan']);
    }

    public function test_dashboard_endpoint_returns_financials_for_super_admin_only(): void
    {
        $admin = $this->createUserWithRole('super-admin');
        $sales = $this->createUserWithRole('sales');

        $adminResponse = $this->actingAs($admin)->getJson('/api/dashboard');
        $adminResponse->assertOk()->assertJsonStructure([
            'role', 'summary',
            'financials' => ['total_pendapatan', 'total_beban', 'laba_bersih', 'pendapatan_by_category', 'beban_by_category', 'monthly_trend'],
        ]);

        $salesResponse = $this->actingAs($sales)->getJson('/api/dashboard');
        $salesResponse->assertOk();
        $this->assertArrayNotHasKey('financials', $salesResponse->json());
    }
}
