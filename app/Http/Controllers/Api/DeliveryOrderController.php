<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesBillingData;
use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\Transaction;
use App\Services\Billing\DeliveryOrderBuilder;
use Illuminate\Http\Request;

class DeliveryOrderController extends Controller
{
    use ScopesBillingData;

    public function __construct(private DeliveryOrderBuilder $deliveryOrderBuilder) {}

    public function index(Request $request)
    {
        $query = DeliveryOrder::query()->with(['customer:id,name,customer_number']);
        $this->scopeByRole($query, $request->user());

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('delivery_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('delivery_date', '<=', $to);
        }

        return response()->json($query->orderByDesc('delivery_date')->orderByDesc('id')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'transaction_ids' => ['required', 'array', 'min:1'],
            'transaction_ids.*' => ['integer', 'exists:transactions,id'],
            'delivery_date' => ['nullable', 'date'],
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'courier' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $transactions = Transaction::with('product')->whereIn('id', $data['transaction_ids'])->get();

        try {
            $deliveryOrder = $this->deliveryOrderBuilder->build($transactions, $data, $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json($deliveryOrder, 201);
    }

    public function show(Request $request, DeliveryOrder $deliveryOrder)
    {
        $deliveryOrder->load([
            'customer', 'reseller:id,name', 'sales:id,name', 'collector:id,name',
            'transactions.product', 'creator:id,name',
        ]);

        return response()->json($deliveryOrder);
    }

    public function ship(Request $request, DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status !== 'pending') {
            abort(422, 'Hanya delivery order berstatus pending yang bisa dikirim.');
        }

        $data = $request->validate([
            'courier' => ['nullable', 'string', 'max:255'],
            'tracking_number' => ['nullable', 'string', 'max:255'],
        ]);

        $deliveryOrder->fill($data);
        $deliveryOrder->status = 'shipped';
        $deliveryOrder->shipped_at = now();
        $deliveryOrder->save();

        return response()->json($deliveryOrder->fresh(['transactions.product', 'customer']));
    }

    public function deliver(Request $request, DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status !== 'shipped') {
            abort(422, 'Hanya delivery order berstatus shipped yang bisa ditandai diterima.');
        }

        $data = $request->validate([
            'recipient_name' => ['nullable', 'string', 'max:255'],
        ]);

        $deliveryOrder->fill($data);
        $deliveryOrder->status = 'delivered';
        $deliveryOrder->delivered_at = now();
        $deliveryOrder->save();

        return response()->json($deliveryOrder->fresh(['transactions.product', 'customer']));
    }

    public function cancel(DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status !== 'pending') {
            abort(422, 'Hanya delivery order berstatus pending yang bisa dibatalkan.');
        }

        $deliveryOrder->transactions()->update(['delivery_order_id' => null]);
        $deliveryOrder->status = 'cancelled';
        $deliveryOrder->save();

        return response()->json($deliveryOrder);
    }
}
