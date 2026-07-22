<?php

namespace Tests\Feature\Reports;

use App\Services\Billing\InvoiceBuilder;
use App\Services\Billing\PaymentService;
use App\Services\Billing\TransactionService;
use App\Services\Reports\BillingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBillingData;
use Tests\TestCase;

class BillingReportServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBillingData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();
    }

    private function makeInvoice(int $customerId, int $productId, string $status, float $amountPaid = 0): \App\Models\Invoice
    {
        $transaction = app(TransactionService::class)->create(['customer_id' => $customerId, 'product_id' => $productId]);
        $invoice = app(InvoiceBuilder::class)->build(collect([$transaction]));

        if ($amountPaid > 0) {
            app(PaymentService::class)->record($invoice, [
                'amount' => $amountPaid,
                'payment_date' => now()->toDateString(),
                'status' => 'confirmed',
            ]);
        }

        return $invoice->fresh();
    }

    public function test_transaction_report_summary_matches_sql_aggregation_and_paginates(): void
    {
        $reseller = $this->createUserWithRole('reseller');
        $product = $this->createInternetProduct(['price' => 200000]);

        for ($i = 0; $i < 3; $i++) {
            $customer = $this->createCustomer(['reseller_id' => $reseller->id]);
            $this->makeInvoice($customer->id, $product->id, 'lunas', 222000);
        }
        $customer = $this->createCustomer(['reseller_id' => $reseller->id]);
        $this->makeInvoice($customer->id, $product->id, 'belum_lunas');

        $result = app(BillingReportService::class)->transactionReport($reseller->id, null, null, 2);

        $this->assertSame(4, $result['summary']['jumlah_invoice']);
        $this->assertSame(3, $result['summary']['lunas']);
        $this->assertSame(0, $result['summary']['cicilan']);
        $this->assertSame(1, $result['summary']['belum_lunas']);
        $this->assertSame(888000.0, $result['summary']['total_tagihan']);
        $this->assertSame(666000.0, $result['summary']['total_terbayar']);

        $this->assertSame(4, $result['invoices']->total());
        $this->assertSame(2, $result['invoices']->count());
        $this->assertSame(2, $result['invoices']->lastPage());
    }

    public function test_transaction_report_scopes_to_reseller_and_excludes_other_resellers_invoices(): void
    {
        $resellerA = $this->createUserWithRole('reseller');
        $resellerB = $this->createUserWithRole('reseller');
        $product = $this->createInternetProduct();

        $customerA = $this->createCustomer(['reseller_id' => $resellerA->id]);
        $this->makeInvoice($customerA->id, $product->id, 'belum_lunas');

        $customerB = $this->createCustomer(['reseller_id' => $resellerB->id]);
        $this->makeInvoice($customerB->id, $product->id, 'belum_lunas');

        $result = app(BillingReportService::class)->transactionReport($resellerA->id, null, null, 20);

        $this->assertSame(1, $result['summary']['jumlah_invoice']);
    }

    public function test_dashboard_summary_uses_sql_aggregation(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createInternetProduct(['price' => 200000]);
        $this->makeInvoice($customer->id, $product->id, 'lunas', 222000);
        $this->makeInvoice($customer->id, $product->id, 'belum_lunas');

        $summary = app(BillingReportService::class)->dashboardSummary(null, null);

        $this->assertSame(444000.0, $summary['total_omzet']);
        $this->assertSame(222000.0, $summary['total_terbayar']);
        $this->assertSame(222000.0, $summary['total_piutang']);
        $this->assertSame(1, $summary['invoice_lunas']);
        $this->assertSame(1, $summary['invoice_belum_lunas']);
    }

    public function test_sales_report_summary_and_pagination(): void
    {
        $sales = $this->createUserWithRole('sales', ['commission_type' => 'flat', 'commission_value' => 25000]);
        $customer = $this->createCustomer(['sales_id' => $sales->id]);
        $product = $this->createInternetProduct(['price' => 200000]);

        for ($i = 0; $i < 3; $i++) {
            $customer = $this->createCustomer(['sales_id' => $sales->id]);
            $this->makeInvoice($customer->id, $product->id, 'lunas', 222000);
        }

        $result = app(BillingReportService::class)->salesReport($sales->id, null, null, 2);

        $this->assertSame(3, $result['summary']['jumlah_transaksi_komisi']);
        $this->assertSame(75000.0, $result['summary']['total_komisi']);
        $this->assertSame(75000.0, $result['summary']['komisi_pending']);
        $this->assertSame(0.0, $result['summary']['komisi_paid']);
        $this->assertSame(3, $result['commissions']->total());
        $this->assertSame(2, $result['commissions']->count());
    }

    public function test_collector_report_returns_two_independently_paginated_lists(): void
    {
        $collector = $this->createUserWithRole('collector');
        $product = $this->createInternetProduct(['price' => 200000]);

        for ($i = 0; $i < 3; $i++) {
            $customer = $this->createCustomer(['collector_id' => $collector->id]);
            $this->makeInvoice($customer->id, $product->id, 'belum_lunas');
        }
        for ($i = 0; $i < 2; $i++) {
            $customer = $this->createCustomer(['collector_id' => $collector->id]);
            $this->makeInvoice($customer->id, $product->id, 'lunas', 222000);
        }

        $result = app(BillingReportService::class)->collectorReport($collector->id, null, null, 2);

        $this->assertSame(3, $result['summary']['jumlah_perlu_ditagih']);
        $this->assertSame(2, $result['summary']['jumlah_konfirmasi']);
        $this->assertSame(444000.0, $result['summary']['total_berhasil_ditagih']);

        $this->assertSame(3, $result['perlu_ditagih']->total());
        $this->assertSame(2, $result['perlu_ditagih']->count());
        $this->assertSame(2, $result['riwayat_pembayaran']->total());
    }
}
