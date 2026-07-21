<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        return response()->json($query->orderBy('name')->paginate($request->integer('per_page', 20)));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['code'] = $data['code'] ?? strtoupper('PRD-'.str_pad((string) (Product::max('id') + 1), 4, '0', STR_PAD_LEFT));

        return response()->json(Product::create($data), 201);
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }

    public function update(Request $request, Product $product)
    {
        $product->update($this->validated($request, $product->id));

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(['message' => 'Produk dihapus']);
    }

    private function validated(Request $request, ?int $productId = null): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:30', 'unique:products,code,'.$productId],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:jasa,barang'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'is_recurring' => ['boolean'],
            'recurring_period' => ['nullable', 'in:monthly,quarterly,yearly'],
            'is_ppn_applicable' => ['boolean'],
            'is_telco_levy_applicable' => ['boolean'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
