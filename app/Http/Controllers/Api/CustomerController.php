<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ScopesBillingData;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ScopesBillingData;

    public function index(Request $request)
    {
        $query = Customer::query()->with(['reseller:id,name', 'sales:id,name', 'collector:id,name']);
        $this->scopeByRole($query, $request->user());

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_number', 'like', "%{$search}%")
                    ->orWhere('phone1', 'like', "%{$search}%");
            });
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->orderByDesc('id')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'phone1' => ['required', 'string', 'max:30'],
            'phone2' => ['nullable', 'string', 'max:30'],
            'reseller_id' => ['nullable', 'exists:users,id'],
            'sales_id' => ['nullable', 'exists:users,id'],
            'collector_id' => ['nullable', 'exists:users,id'],
            'activation_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:prospect,active,suspended,terminated'],
            'notes' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        if ($user->hasRole('reseller')) {
            $data['reseller_id'] = $user->id;
        }
        if ($user->hasRole('sales')) {
            $data['sales_id'] = $user->id;
            $data['reseller_id'] = $user->parent_reseller_id;
        }

        $data['customer_number'] = 'CUST-'.str_pad((string) (Customer::max('id') + 1), 6, '0', STR_PAD_LEFT);
        $data['status'] = $data['status'] ?? 'active';

        $customer = Customer::create($data);

        return response()->json($customer, 201);
    }

    public function show(Request $request, Customer $customer)
    {
        $this->authorizeAccess($request, $customer);
        $customer->load(['reseller:id,name', 'sales:id,name', 'collector:id,name']);

        return response()->json($customer);
    }

    public function update(Request $request, Customer $customer)
    {
        $this->authorizeAccess($request, $customer);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string'],
            'phone1' => ['sometimes', 'string', 'max:30'],
            'phone2' => ['nullable', 'string', 'max:30'],
            'reseller_id' => ['nullable', 'exists:users,id'],
            'sales_id' => ['nullable', 'exists:users,id'],
            'collector_id' => ['nullable', 'exists:users,id'],
            'status' => ['nullable', 'in:prospect,active,suspended,terminated'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer->update($data);

        return response()->json($customer);
    }

    public function destroy(Request $request, Customer $customer)
    {
        $this->authorizeAccess($request, $customer);
        $customer->delete();

        return response()->json(['message' => 'Customer dihapus']);
    }

    private function authorizeAccess(Request $request, Customer $customer): void
    {
        $user = $request->user();
        if ($user->hasRole('super-admin')) {
            return;
        }
        if ($user->hasRole('reseller') && $customer->reseller_id === $user->id) {
            return;
        }
        if ($user->hasRole('sales') && $customer->sales_id === $user->id) {
            return;
        }
        if ($user->hasRole('collector') && $customer->collector_id === $user->id) {
            return;
        }

        abort(403, 'Anda tidak memiliki akses ke pelanggan ini.');
    }
}
