<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerDashboardController extends Controller
{
    /**
     * GET /api/customer/dashboard
     * Summary metrics and recent activity for the authenticated customer.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $cartQuery = Cart::query()->where('user_id', $user->id);
        $orderQuery = OrderItem::query()->where('user_id', $user->id);

        $orderCounts = (clone $orderQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $cartPreview = (clone $cartQuery)
            ->with([
                'product.images:id,product_id,image_path,sort_order',
                'product.category:id,name',
                'product.store:id,uuid,store_name',
                'variant:id,product_id,name,price,stock',
            ])
            ->latest()
            ->take(4)
            ->get()
            ->map(function (Cart $item) {
                return [
                    'id' => $item->id,
                    'quantity' => $item->quantity,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'product' => $item->product,
                    'variant' => $item->variant,
                    'line_total' => (float) ($item->variant?->price ?? 0) * $item->quantity,
                ];
            })
            ->values();

        $recentOrders = (clone $orderQuery)
            ->with([
                'product.images:id,product_id,image_path,sort_order',
                'product.store:id,uuid,store_name',
                'variant:id,product_id,name,price,stock',
                'review:id,order_item_id,rating,review',
            ])
            ->latest()
            ->take(5)
            ->get()
            ->map(function (OrderItem $item) {
                $lineTotal = (float) $item->price * $item->quantity;

                return [
                    'id' => $item->id,
                    'checkout_no' => $item->checkout_no,
                    'status' => $item->status,
                    'created_at' => $item->created_at,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'shipping_cost' => (float) $item->shipping_cost,
                    'item_total' => $item->status === 'cancelled' ? 0 : $lineTotal + (float) $item->shipping_cost,
                    'product' => $item->product,
                    'variant' => $item->variant,
                    'store' => $item->product?->store,
                    'review' => $item->review ? [
                        'id' => $item->review->id,
                        'rating' => $item->review->rating,
                        'review' => $item->review->review,
                    ] : null,
                    'can_review' => $item->status === 'delivered' && !$item->review,
                ];
            })
            ->values();

        $latestProducts = Product::query()
            ->where('status', 'active')
            ->whereHas('variants', function ($query) {
                $query->where('stock', '>', 0);
            })
            ->whereHas('store.verification', function ($query) {
                $query->where('store_status', 'approved');
            })
            ->with(['images:id,product_id,image_path,sort_order', 'category:id,name', 'store:id,uuid,store_name', 'variants:id,product_id,name,price,stock'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->withSum([
                'orderItems as sold' => fn ($orderQuery) => $orderQuery->where('status', 'delivered'),
            ], 'quantity')
            ->latest('created_at')
            ->limit(4)
            ->get()
            ->map(fn (Product $product) => $this->mapProduct($product))
            ->values();

        $popularProducts = Product::query()
            ->where('status', 'active')
            ->whereHas('variants', function ($query) {
                $query->where('stock', '>', 0);
            })
            ->whereHas('store.verification', function ($query) {
                $query->where('store_status', 'approved');
            })
            ->with(['images:id,product_id,image_path,sort_order', 'category:id,name', 'store:id,uuid,store_name', 'variants:id,product_id,name,price,stock'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->withSum([
                'orderItems as sold' => fn ($orderQuery) => $orderQuery->where('status', 'delivered'),
            ], 'quantity')
            ->orderByDesc('sold')
            ->orderByDesc('reviews_avg_rating')
            ->orderByDesc('reviews_count')
            ->limit(4)
            ->get()
            ->map(fn (Product $product) => $this->mapProduct($product))
            ->values();

        return response()->json([
            'stats' => [
                'cart_items' => (clone $cartQuery)->sum('quantity'),
                'cart_lines' => (clone $cartQuery)->count(),
                'total_orders' => (clone $orderQuery)->count(),
                'pending_orders' => (int) ($orderCounts['pending'] ?? 0),
                'processing_orders' => (int) ($orderCounts['processing'] ?? 0),
                'shipped_orders' => (int) ($orderCounts['shipped'] ?? 0),
                'delivered_orders' => (int) ($orderCounts['delivered'] ?? 0),
                'cancelled_orders' => (int) ($orderCounts['cancelled'] ?? 0),
                'reviews_given' => ProductReview::query()->where('user_id', $user->id)->count(),
                'spend_total' => (float) (clone $orderQuery)
                    ->whereNotIn('status', ['cancelled'])
                    ->selectRaw('COALESCE(SUM((price * quantity) + shipping_cost), 0) as total')
                    ->value('total'),
            ],
            'cart_preview' => $cartPreview,
            'recent_orders' => $recentOrders,
            'latest_products' => $latestProducts,
            'popular_products' => $popularProducts,
        ]);
    }

    private function mapProduct(Product $product): array
    {
        $payload = $product->toArray();
        $payload['rating'] = round((float) ($product->reviews_avg_rating ?? 0), 1);
        $payload['review_count'] = (int) ($product->reviews_count ?? 0);
        $payload['sold'] = (int) ($product->sold ?? 0);

        return $payload;
    }
}
