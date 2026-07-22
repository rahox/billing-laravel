<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Billing\TransactionService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(private TransactionService $transactionService) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Transaction::query()->with(['customer:id,name,customer_number', 'product:id,name,type', 'discount:id,name']);

        if (! $user->hasRole('super-admin')) {
            $query->whereHas('customer', function ($q) use ($user) {
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
        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }
        if ($productType = $request->query('product_type')) {
            $query->whereHas('product', fn ($q) => $q->where('type', $productType));
        }
        if ($request->boolean('undelivered_only')) {
            $query->whereNull('delivery_order_id');
        }

        return response()->json($query->orderByDesc('id')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'product_id' => ['required', 'exists:products,id'],
            'discount_id' => ['nullable', 'exists:discounts,id'],
            'qty' => ['nullable', 'integer', 'min:1'],
            'transaction_date' => ['nullable', 'date'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
            'activation_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $data['created_by'] = $request->user()->id;
        $transaction = $this->transactionService->create($data);

        return response()->json($transaction->load(['customer', 'product', 'discount']), 201);
    }

    public function show(Transaction $transaction)
    {
        return response()->json($transaction->load(['customer', 'product', 'discount', 'invoice']));
    }

    public function destroy(Transaction $transaction)
    {
        if ($transaction->status !== 'draft') {
            abort(422, 'Hanya transaksi berstatus draft yang bisa dihapus.');
        }
        $transaction->delete();

        return response()->json(['message' => 'Transaksi dihapus']);
    }
}
