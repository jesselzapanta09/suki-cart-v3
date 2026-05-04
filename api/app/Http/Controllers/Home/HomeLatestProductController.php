<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\Product;

class HomeLatestProductController extends Controller
{
    /**
     * GET /api/home/latest-products
     * Return the 4 newest public products.
     */
    public function index()
    {
        $products = Product::query()
            ->where('status', 'active')
            ->whereHas('variants', function ($query) {
                $query->where('stock', '>', 0);
            })
            ->whereHas('store.verification', function ($query) {
                $query->where('store_status', 'approved');
            })
            ->with(['images', 'category', 'store', 'variants'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->withSum([
                'orderItems as sold' => fn ($orderQuery) => $orderQuery->where('status', 'delivered'),
            ], 'quantity')
            ->latest('created_at')
            ->limit(4)
            ->get()
            ->map(function (Product $product) {
                $payload = $product->toArray();
                $payload['rating'] = round((float) ($product->reviews_avg_rating ?? 0), 1);
                $payload['review_count'] = (int) ($product->reviews_count ?? 0);
                $payload['sold'] = (int) ($product->sold ?? 0);

                return $payload;
            })
            ->values();

        return response()->json($products);
    }
}
