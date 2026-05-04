<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerDashboardController extends Controller
{
    /**
     * GET /api/seller/dashboard
     * Summary metrics and recent activity for the authenticated seller.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user->store;

        if (!$store) {
            return response()->json([
                'stats' => [
                    'total_products' => 0,
                    'active_products' => 0,
                    'out_of_stock_products' => 0,
                    'total_stock' => 0,
                    'total_orders' => 0,
                    'pending_orders' => 0,
                    'processing_orders' => 0,
                    'shipped_orders' => 0,
                    'delivered_orders' => 0,
                    'cancelled_orders' => 0,
                    'lifetime_revenue' => 0,
                ],
                'recent_products' => [],
                'recent_orders' => [],
                'store' => null,
            ]);
        }

        $store->load(['category:id,name', 'verification:id,store_id,store_status,rejection_reason,reviewed_at']);

        $productQuery = Product::query()->where('store_id', $store->id);
        $orderBaseQuery = OrderItem::query()
            ->whereHas('product', fn ($query) => $query->where('store_id', $store->id));

        $orderCounts = (clone $orderBaseQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentProducts = (clone $productQuery)
            ->with([
                'category:id,name',
                'images:id,product_id,image_path,sort_order',
                'variants:id,product_id,name,price,stock',
            ])
            ->latest()
            ->take(4)
            ->get([
                'id',
                'uuid',
                'store_id',
                'name',
                'category_id',
                'status',
                'created_at',
            ]);

        $recentOrders = (clone $orderBaseQuery)
            ->with([
                'user:id,firstname,lastname,email',
                'product:id,uuid,store_id,name',
                'product.images:id,product_id,image_path,sort_order',
                'variant:id,product_id,name,price,stock',
            ])
            ->latest()
            ->take(5)
            ->get()
            ->map(function (OrderItem $item) {
                return [
                    'id' => $item->id,
                    'checkout_no' => $item->checkout_no,
                    'status' => $item->status,
                    'created_at' => $item->created_at,
                    'quantity' => $item->quantity,
                    'price' => (float) $item->price,
                    'shipping_cost' => (float) $item->shipping_cost,
                    'item_total' => $item->status === 'cancelled'
                        ? 0
                        : ((float) $item->price * $item->quantity) + (float) $item->shipping_cost,
                    'customer' => $item->user ? [
                        'id' => $item->user->id,
                        'firstname' => $item->user->firstname,
                        'lastname' => $item->user->lastname,
                        'email' => $item->user->email,
                    ] : null,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'uuid' => $item->product->uuid,
                        'name' => $item->product->name,
                        'images' => $item->product->images,
                    ] : null,
                    'variant' => $item->variant ? [
                        'id' => $item->variant->id,
                        'name' => $item->variant->name,
                    ] : null,
                ];
            })
            ->values();

        $lifetimeRevenue = (clone $orderBaseQuery)
            ->whereNotIn('status', ['cancelled'])
            ->selectRaw('COALESCE(SUM((price * quantity) + shipping_cost), 0) as total')
            ->value('total');

        $totalStock = ProductVariant::query()
            ->whereHas('product', fn ($query) => $query->where('store_id', $store->id))
            ->sum('stock');

        return response()->json([
            'stats' => [
                'total_products' => (clone $productQuery)->count(),
                'active_products' => (clone $productQuery)->where('status', 'active')->count(),
                'out_of_stock_products' => (clone $productQuery)->where('status', 'out_of_stock')->count(),
                'total_stock' => (int) $totalStock,
                'total_orders' => (clone $orderBaseQuery)->count(),
                'pending_orders' => (int) ($orderCounts['pending'] ?? 0),
                'processing_orders' => (int) ($orderCounts['processing'] ?? 0),
                'shipped_orders' => (int) ($orderCounts['shipped'] ?? 0),
                'delivered_orders' => (int) ($orderCounts['delivered'] ?? 0),
                'cancelled_orders' => (int) ($orderCounts['cancelled'] ?? 0),
                'lifetime_revenue' => (float) $lifetimeRevenue,
            ],
            'recent_products' => $recentProducts,
            'recent_orders' => $recentOrders,
            'store' => [
                'id' => $store->id,
                'uuid' => $store->uuid,
                'store_name' => $store->store_name,
                'description' => $store->description,
                'verified_at' => $store->verified_at,
                'created_at' => $store->created_at,
                'category' => $store->category,
                'verification' => $store->verification,
            ],
        ]);
    }
}
