<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\Request;

class DiscountController extends Controller
{
    public function index(Request $request)
    {
        $query = Discount::query();
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        return response()->json($query->orderBy('name')->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request)
    {
        return response()->json(Discount::create($this->validated($request)), 201);
    }

    public function show(Discount $discount)
    {
        return response()->json($discount);
    }

    public function update(Request $request, Discount $discount)
    {
        $discount->update($this->validated($request));

        return response()->json($discount);
    }

    public function destroy(Discount $discount)
    {
        $discount->delete();

        return response()->json(['message' => 'Diskon dihapus']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:flat,percentage'],
            'mode' => ['required', 'in:manual,prorate_activation'],
            'value' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
