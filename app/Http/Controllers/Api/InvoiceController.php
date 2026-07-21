<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesBillingData;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Services\Billing\InvoiceBuilder;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use ScopesBillingData;

    public function __construct(private InvoiceBuilder $invoiceBuilder) {}

    public function index(Request $request)
    {
        $query = Invoice::query()->with(['customer:id,name,customer_number']);
        $this->scopeByRole($query, $request->user());

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('invoice_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('invoice_date', '<=', $to);
        }

        return response()->json($query->orderByDesc('invoice_date')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'exists:transactions,id'],
            'invoice_date' => ['nullable', 'date'],
            'due_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $transactions = Transaction::whereIn('id', $data['transaction_ids'])->get();

        $invoice = $this->invoiceBuilder->build(
            $transactions,
            $data['invoice_date'] ?? null,
            $data['due_days'] ?? 14,
            $request->user()->id,
        );

        return response()->json($invoice, 201);
    }

    public function show(Request $request, Invoice $invoice)
    {
        $invoice->load(['customer', 'reseller:id,name', 'sales:id,name', 'collector:id,name', 'transactions.product', 'payments', 'commissions']);

        return response()->json($invoice);
    }
}
