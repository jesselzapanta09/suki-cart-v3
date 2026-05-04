<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class AdminProductController extends Controller
{
    /**
     * GET /api/admin/products
     * Read-only list of active products for admin users.
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->where('status', 'active');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('store', function ($storeQuery) use ($search) {
                        $storeQuery->where('store_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        $sortField = $request->input('sort_field', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['id', 'name', 'created_at'];
        if (in_array($sortField, $allowedSorts, true)) {
            $query->orderBy($sortField, $sortOrder === 'ascend' ? 'asc' : 'desc');
        }

        $perPage = (int) $request->input('per_page', 10);
        $products = $query
            ->with(['images', 'category', 'variants', 'store.user'])
            ->paginate($perPage);

        return response()->json($products);
    }

    /**
     * GET /api/admin/products/{uuid}
     * Read-only active product detail for admin users.
     */
    public function show(string $uuid)
    {
        $product = Product::query()
            ->where('status', 'active')
            ->where('uuid', $uuid)
            ->with(['images', 'category', 'variants', 'store.user', 'store.category'])
            ->firstOrFail();

        return response()->json([
            'product' => $product,
        ]);
    }
}
