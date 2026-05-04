<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;

class HomeStoreController extends Controller
{
    public function show(int $id)
    {
        $store = Store::query()
            ->whereKey($id)
            ->whereHas('verification', function ($query) {
                $query->where('store_status', 'approved');
            })
            ->with(['category'])
            ->firstOrFail();

        $productCount = Product::query()
            ->where('store_id', $store->id)
            ->where('status', 'active')
            ->whereHas('variants', function ($query) {
                $query->where('stock', '>', 0);
            })
            ->count();

        return response()->json([
            'store' => [
                'id' => $store->id,
                'store_name' => $store->store_name,
                'description' => $store->description,
                'banner' => $store->banner,
                'category' => $store->category ? [
                    'id' => $store->category->id,
                    'name' => $store->category->name,
                ] : null,
                'products_count' => $productCount,
            ],
        ]);
    }
}
