<?php

namespace Tests\Feature\Billing;

use App\Models\Commission;
use App\Models\JournalEntry;
use App\Services\Billing\InvoiceBuilder;
use App\Services\Billing\PaymentService;
use App\Services\Billing\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBillingData;
use Tests\TestCase;

class BillingLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesBillingData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndAccounts();
    }

    private function assertJournalIsBalanced(JournalEntry $entry): void
    {
        $entry->loadMissing('lines');
        $this->assertSame(
            round((float) $entry->lines->sum('debit'), 2),
            round((float) $entry->lines->sum('credit'), 2),
            "Journal entry {$entry->journal_number} is not balanced."
        );
    }

    public function test_invoice_built_from_transaction_posts_a_balanced_journal(): void
    {
        $sales = $this->createUserWithRole('sales', ['commission_type' => 'flat', 'commission_value' => 25000]);
        $customer = $this->createCustomer(['sales_id' => $sales->id]);
        $product = $this->createInternetProduct(['price' => 200000]);

        $transaction = app(TransactionService::class)->create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'qty' => 1,
        ]);

        $this->assertSame('draft', $transaction->status);

        $invoice = app(InvoiceBuilder::class)->build(collect([$transaction]));

        $this->assertSame(200000.0, (float) $invoice->subtotal);
        $this->assertSame(22000.0, (float) $invoice->ppn_total);
        $this->assertSame(222000.0, (float) $invoice->grand_total);
        $this->assertSame('invoiced', $transaction->fresh()->status);

        $journal = JournalEntry::where('reference_type', 'invoice')->where('reference_id', $invoice->id)->firstOrFail();
        $this->assertJournalIsBalanced($journal);
    }

    public function test_invoice_builder_rejects_transactions_from_different_customers(): void
    {
        $customerA = $this->createCustomer();
        $customerB = $this->createCustomer();
        $product = $this->createInternetProduct();

        $trxA = app(TransactionService::class)->create(['customer_id' => $customerA->id, 'product_id' => $product->id]);
        $trxB = app(TransactionService::class)->create(['customer_id' => $customerB->id, 'product_id' => $product->id]);

        $this->expectException(\InvalidArgumentException::class);

        app(InvoiceBuilder::class)->build(collect([$trxA, $trxB]));
    }

    public function test_confirming_full_payment_marks_invoice_as_lunas_and_posts_balanced_journal(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createInternetProduct(['price' => 200000]);
        $transaction = app(TransactionService::class)->create(['customer_id' => $customer->id, 'product_id' => $product->id]);
        $invoice = app(InvoiceBuilder::class)->build(collect([$transaction]));

        $payment = app(PaymentService::class)->record($invoice, [
            'amount' => $invoice->grand_total,
            'payment_date' => now()->toDateString(),
            'status' => 'confirmed',
        ]);

        $this->assertSame('confirmed', $payment->status);
        $this->assertSame('lunas', $invoice->fresh()->status);
        $this->assertSame((float) $invoice->grand_total, (float) $invoice->fresh()->paid_amount);

        $journal = JournalEntry::where('reference_type', 'payment')->where('reference_id', $payment->id)->firstOrFail();
        $this->assertJournalIsBalanced($journal);
    }

    public function test_partial_payment_marks_invoice_as_cicilan(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createInternetProduct(['price' => 200000]);
        $transaction = app(TransactionService::class)->create(['customer_id' => $customer->id, 'product_id' => $product->id]);
        $invoice = app(InvoiceBuilder::class)->build(collect([$transaction]));

        app(PaymentService::class)->record($invoice, [
            'amount' => (float) $invoice->grand_total / 2,
            'payment_date' => now()->toDateString(),
            'status' => 'confirmed',
        ]);

        $this->assertSame('cicilan', $invoice->fresh()->status);
    }

    public function test_unpaid_invoice_past_due_date_becomes_overdue_on_refresh(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createInternetProduct();
        $transaction = app(TransactionService::class)->create(['customer_id' => $customer->id, 'product_id' => $product->id]);
        $invoice = app(InvoiceBuilder::class)->build(collect([$transaction]), now()->subDays(30)->toDateString(), 14);

        $invoice->refreshStatus();

        $this->assertSame('overdue', $invoice->fresh()->status);
    }

    public function test_flat_commission_is_accrued_once_per_invoice_on_first_confirmed_payment(): void
    {
        $sales = $this->createUserWithRole('sales', ['commission_type' => 'flat', 'commission_value' => 25000]);
        $customer = $this->createCustomer(['sales_id' => $sales->id]);
        $product = $this->createInternetProduct(['price' => 200000]);
        $transaction = app(TransactionService::class)->create(['customer_id' => $customer->id, 'product_id' => $product->id]);
        $invoice = app(InvoiceBuilder::class)->build(collect([$transaction]));

        $paymentService = app(PaymentService::class);

        $firstPayment = $paymentService->record($invoice, [
            'amount' => (float) $invoice->grand_total / 2,
            'payment_date' => now()->toDateString(),
            'status' => 'confirmed',
        ]);
        $secondPayment = $paymentService->record($invoice, [
            'amount' => (float) $invoice->grand_total / 2,
            'payment_date' => now()->toDateString(),
            'status' => 'confirmed',
        ]);

        $this->assertSame(1, Commission::where('invoice_id', $invoice->id)->count());
        $commission = Commission::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(25000.0, (float) $commission->commission_amount);

        $journal = JournalEntry::where('reference_type', 'commission')->where('reference_id', $commission->id)->firstOrFail();
        $this->assertJournalIsBalanced($journal);
    }

    public function test_percentage_commission_is_accrued_proportionally_per_payment(): void
    {
        $sales = $this->createUserWithRole('sales', ['commission_type' => 'percentage', 'commission_value' => 5]);
        $customer = $this->createCustomer(['sales_id' => $sales->id]);
        $product = $this->createInternetProduct(['price' => 200000]);
        $transaction = app(TransactionService::class)->create(['customer_id' => $customer->id, 'product_id' => $product->id]);
        $invoice = app(InvoiceBuilder::class)->build(collect([$transaction]));

        $paymentService = app(PaymentService::class);
        $paymentService->record($invoice, [
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'status' => 'confirmed',
        ]);
        $paymentService->record($invoice, [
            'amount' => 122000,
            'payment_date' => now()->toDateString(),
            'status' => 'confirmed',
        ]);

        $this->assertSame(2, Commission::where('invoice_id', $invoice->id)->count());
        $this->assertSame(5000.0, (float) Commission::where('invoice_id', $invoice->id)->orderBy('id')->first()->commission_amount);
        $this->assertSame(6100.0, (float) Commission::where('invoice_id', $invoice->id)->orderByDesc('id')->first()->commission_amount);
    }

    public function test_no_commission_accrued_when_sales_has_no_commission_plan(): void
    {
        $sales = $this->createUserWithRole('sales', ['commission_type' => null, 'commission_value' => null]);
        $customer = $this->createCustomer(['sales_id' => $sales->id]);
        $product = $this->createInternetProduct();
        $transaction = app(TransactionService::class)->create(['customer_id' => $customer->id, 'product_id' => $product->id]);
        $invoice = app(InvoiceBuilder::class)->build(collect([$transaction]));

        app(PaymentService::class)->record($invoice, [
            'amount' => (float) $invoice->grand_total,
            'payment_date' => now()->toDateString(),
            'status' => 'confirmed',
        ]);

        $this->assertSame(0, Commission::where('invoice_id', $invoice->id)->count());
    }

    public function test_device_transaction_posts_cogs_journal_lines(): void
    {
        $customer = $this->createCustomer();
        $product = $this->createDeviceProduct(['price' => 350000, 'cost_price' => 250000]);
        $transaction = app(TransactionService::class)->create(['customer_id' => $customer->id, 'product_id' => $product->id]);
        $invoice = app(InvoiceBuilder::class)->build(collect([$transaction]));

        $journal = JournalEntry::where('reference_type', 'invoice')->where('reference_id', $invoice->id)->firstOrFail();
        $this->assertJournalIsBalanced($journal);

        $hppLine = $journal->lines()->whereHas('account', fn ($q) => $q->where('code', '5-1000'))->first();
        $this->assertNotNull($hppLine);
        $this->assertSame(250000.0, (float) $hppLine->debit);
    }
}
