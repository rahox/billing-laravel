<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $paymentService) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Payment::query()->with(['invoice.customer', 'collector:id,name']);

        if (! $user->hasRole('super-admin')) {
            $query->whereHas('invoice', function ($q) use ($user) {
                if ($user->hasRole('reseller')) {
                    $q->where('reseller_id', $user->id);
                } elseif ($user->hasRole('sales')) {
                    $q->where('sales_id', $user->id);
                } elseif ($user->hasRole('collector')) {
                    $q->where('collector_id', $user->id);
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->orderByDesc('payment_date')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'method' => ['nullable', 'in:cash,transfer,ewallet,other'],
            'status' => ['nullable', 'in:pending,confirmed'],
            'notes' => ['nullable', 'string'],
        ]);

        $invoice = Invoice::findOrFail($data['invoice_id']);
        $data['collector_id'] = $request->user()->id;
        if (($data['status'] ?? 'pending') === 'confirmed') {
            $data['confirmed_by'] = $request->user()->id;
        }

        $payment = $this->paymentService->record($invoice, $data);

        return response()->json($payment->load('invoice'), 201);
    }

    public function confirm(Request $request, Payment $payment)
    {
        $payment = $this->paymentService->confirm($payment, $request->user()->id);

        return response()->json($payment->load('invoice'));
    }
}
