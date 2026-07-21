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
}
