<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreOrderRequest;
use App\Jobs\SendNotificationJob;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Services\ShippingCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerOrderController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 15), 50);

        $baseQuery = Order::query()
            ->where('user_id', $user->id);

        $filteredBaseQuery = (clone $baseQuery)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('uuid', 'like', "%{$search}%")
                        ->orWhereHas('items.product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                });
            });

        $statusCounts = (clone $filteredBaseQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $orders = (clone $filteredBaseQuery)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with($this->customerOrderRelations())
            ->when(
                !$status,
                fn ($q) => $q->orderByRaw("
                    CASE status
                        WHEN 'pending' THEN 1
                        WHEN 'processing' THEN 2
                        WHEN 'shipped' THEN 3
                        WHEN 'delivered' THEN 4
                        WHEN 'cancelled' THEN 5
                        ELSE 6
                    END
                ")
            )
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'message' => 'Orders retrieved successfully.',
            'data' => collect($orders->items())->map(fn (Order $order) => $this->decorateCustomerOrder($order))->values(),
            'pagination' => [
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ],
            'counts' => [
                'all' => (clone $filteredBaseQuery)->count(),
                'pending' => (int) ($statusCounts['pending'] ?? 0),
                'processing' => (int) ($statusCounts['processing'] ?? 0),
                'shipped' => (int) ($statusCounts['shipped'] ?? 0),
                'delivered' => (int) ($statusCounts['delivered'] ?? 0),
                'cancelled' => (int) ($statusCounts['cancelled'] ?? 0),
            ],
        ]);
    }

    public function show(Request $request, string $uuid)
    {
        $order = $this->customerOrder($request, $uuid, $this->customerOrderRelations());

        return response()->json([
            'message' => 'Order retrieved successfully.',
            'data' => $this->decorateCustomerOrder($order),
        ]);
    }

    public function store(StoreOrderRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();
        $user->locations()->findOrFail($data['location_id']);

        try {
            $preparedItems = $this->prepareCheckoutItems($data['items']);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Please review your order items.',
                'errors' => $e->errors(),
            ], 422);
        }

        $shippingResult = (new ShippingCalculationService())->calculateShipping($data['items']);
        $shippingByStore = collect($shippingResult['breakdown'])->keyBy(fn ($group) => (string) $group['store_id']);

        try {
            $createdOrderIds = DB::transaction(function () use ($user, $data, $preparedItems, $shippingByStore) {
                $createdOrderIds = [];

                foreach ($preparedItems->groupBy('store_id') as $storeId => $storeItems) {
                    $subtotal = round($storeItems->sum(fn ($item) => (float) $item['variant']->price * (int) $item['quantity']), 2);
                    $shippingGroup = $shippingByStore->get((string) $storeId);
                    $shippingCost = (float) ($shippingGroup['shipping_fee'] ?? 0);

                    $order = Order::create([
                        'uuid' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'store_id' => (int) $storeId,
                        'location_id' => $data['location_id'],
                        'address_extra' => $data['address_extra'] ?? null,
                        'status' => 'pending',
                        'shipping_cost' => $shippingCost,
                        'subtotal_amount' => $subtotal,
                        'total_amount' => $subtotal + $shippingCost,
                    ]);

                    foreach ($storeItems as $item) {
                        /** @var ProductVariant $variant */
                        $variant = $item['variant'];

                        if ($variant->stock < $item['quantity']) {
                            throw ValidationException::withMessages([
                                "items.{$item['index']}.quantity" => "Only {$variant->stock} items available for {$variant->name}.",
                            ]);
                        }

                        OrderItem::create([
                            'order_id' => $order->id,
                            'message' => $item['message'],
                            'product_id' => $item['product_id'],
                            'product_variant_id' => $item['product_variant_id'],
                            'quantity' => $item['quantity'],
                            'price' => $variant->price,
                        ]);

                        $variant->decrement('stock', $item['quantity']);
                        $this->syncProductStockStatus($variant->product_id);

                        if (!empty($item['cart_id'])) {
                            Cart::where('user_id', $user->id)->where('id', $item['cart_id'])->delete();
                        } else {
                            Cart::where('user_id', $user->id)
                                ->where('product_id', $item['product_id'])
                                ->where('product_variant_id', $item['product_variant_id'])
                                ->delete();
                        }
                    }

                    $createdOrderIds[] = $order->id;
                }

                return $createdOrderIds;
            });

            $createdOrders = Order::query()
                ->whereIn('id', $createdOrderIds)
                ->with($this->customerOrderRelations())
                ->orderBy('id')
                ->get();

            $this->notifyOrderPlaced($createdOrders);

            return response()->json([
                'message' => 'Orders created successfully.',
                'data' => [
                    'created_count' => $createdOrders->count(),
                    'first_order_uuid' => $createdOrders->first()?->uuid,
                    'orders' => $createdOrders->map(fn (Order $order) => $this->decorateCustomerOrder($order))->values(),
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Please review your order items.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create orders.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancelOrder(Request $request, string $uuid)
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        $order = $this->customerOrder($request, $uuid, $this->customerOrderRelations());

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Cannot cancel order.',
                'error' => 'Only pending orders can be cancelled.',
            ], 422);
        }

        DB::transaction(function () use ($order, $validated) {
            $this->restoreOrderStock($order);
            $order->update([
                'status' => 'cancelled',
                'cancelled_by' => 'customer',
                'cancellation_reason' => $validated['cancellation_reason'],
                'cancelled_at' => now(),
            ]);
        });

        $order->refresh()->load($this->customerOrderRelations());
        $this->notifyOrderCancelled($order, $validated['cancellation_reason']);

        return response()->json([
            'message' => 'Order cancelled successfully.',
            'data' => $this->decorateCustomerOrder($order),
        ]);
    }

    public function deliverOrder(Request $request, string $uuid)
    {
        $order = $this->customerOrder($request, $uuid, $this->customerOrderRelations());

        if ($order->status !== 'shipped') {
            return response()->json([
                'message' => 'Cannot mark order received.',
                'error' => 'Only shipped orders can be marked as received.',
            ], 422);
        }

        $order->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $order->refresh()->load($this->customerOrderRelations());
        $this->notifyOrderDelivered($order);

        return response()->json([
            'message' => 'Order marked as received.',
            'data' => $this->decorateCustomerOrder($order),
        ]);
    }

    public function calculateShipping(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.cart_id' => 'nullable|integer',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.product_variant_id' => 'required|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            return response()->json([
                'message' => 'Shipping calculated successfully.',
                'data' => (new ShippingCalculationService())->calculateShipping($validated['items']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to calculate shipping.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function prepareCheckoutItems(array $items): Collection
    {
        $productIds = collect($items)->pluck('product_id')->unique()->values();
        $variantIds = collect($items)->pluck('product_variant_id')->unique()->values();

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->with('store')
            ->get()
            ->keyBy('id');

        $variants = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        return collect($items)
            ->values()
            ->map(function (array $item, int $index) use ($products, $variants) {
                $product = $products->get($item['product_id']);
                $variant = $variants->get($item['product_variant_id']);
                $quantity = (int) $item['quantity'];

                if (!$product || $product->status !== 'active') {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_id" => 'This product is not available for purchase.',
                    ]);
                }

                if (!$variant || $variant->product_id !== (int) $item['product_id']) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_variant_id" => 'Invalid product variant.',
                    ]);
                }

                if ($variant->stock < $quantity) {
                    throw ValidationException::withMessages([
                        "items.{$index}.quantity" => "Only {$variant->stock} items available for {$variant->name}.",
                    ]);
                }

                return [
                    'index' => $index,
                    'cart_id' => $item['cart_id'] ?? null,
                    'product_id' => (int) $item['product_id'],
                    'product_variant_id' => (int) $item['product_variant_id'],
                    'quantity' => $quantity,
                    'message' => $item['message'] ?? null,
                    'store_id' => (int) $product->store_id,
                    'variant' => $variant,
                ];
            });
    }

    private function customerOrder(Request $request, string $uuid, array $with = []): Order
    {
        return Order::query()
            ->where('user_id', $request->user()->id)
            ->where('uuid', $uuid)
            ->with($with)
            ->firstOrFail();
    }

    private function customerOrderRelations(): array
    {
        return [
            'location',
            'store',
            'items.product.images',
            'items.variant',
            'items.review',
        ];
    }

    private function restoreOrderStock(Order $order): void
    {
        $items = $order->relationLoaded('items') ? $order->items : $order->items()->get();

        foreach ($items as $item) {
            $variant = ProductVariant::find($item->product_variant_id);

            if (!$variant) {
                continue;
            }

            $variant->increment('stock', $item->quantity);
            $this->syncProductStockStatus($variant->product_id);
        }
    }

    private function syncProductStockStatus(int $productId): void
    {
        $product = Product::find($productId);

        if (!$product) {
            return;
        }

        $hasAvailableStock = ProductVariant::query()
            ->where('product_id', $productId)
            ->where('stock', '>', 0)
            ->exists();

        if (!$hasAvailableStock && $product->status !== 'out_of_stock') {
            $product->update(['status' => 'out_of_stock']);

            return;
        }

        if ($hasAvailableStock && $product->status === 'out_of_stock') {
            $product->update(['status' => 'active']);
        }
    }

    private function decorateCustomerOrder(Order $order): array
    {
        $items = $order->items ?? collect();
        $isCancelled = $order->status === 'cancelled';
        $subtotal = $isCancelled ? 0 : (float) $order->subtotal_amount;
        $shippingCost = $isCancelled ? 0 : (float) $order->shipping_cost;

        return [
            'id' => $order->id,
            'uuid' => $order->uuid,
            'created_at' => $order->created_at,
            'status' => $order->status,
            'cancelled_by' => $order->cancelled_by,
            'cancellation_reason' => $order->cancellation_reason,
            'cancelled_at' => $order->cancelled_at,
            'shipped_at' => $order->shipped_at,
            'delivered_at' => $order->delivered_at,
            'location' => $order->location,
            'address_extra' => $order->address_extra,
            'store' => $this->storePayload($order->store),
            'shipment' => $order->courier_name ? [
                'courier_name' => $order->courier_name,
                'tracking_number' => $order->tracking_number,
            ] : null,
            'active_items_count' => $isCancelled ? 0 : $items->count(),
            'cancelled_items_count' => $isCancelled ? $items->count() : 0,
            'subtotal' => $subtotal,
            'shipping_cost' => $shippingCost,
            'total_price' => $subtotal + $shippingCost,
            'can_cancel' => $order->status === 'pending',
            'can_mark_received' => $order->status === 'shipped',
            'order_items' => $items->map(fn (OrderItem $item) => $this->decorateOrderItem($item, $order))->values()->all(),
        ];
    }

    private function decorateOrderItem(OrderItem $item, Order $order): array
    {
        $payload = $item->toArray();
        $payload['line_total'] = (float) $item->price * $item->quantity;
        $payload['status'] = $order->status;
        $payload['store'] = $this->storePayload($order->store);
        $payload['review'] = $this->reviewPayload($item->review);
        $payload['can_review'] = $order->status === 'delivered' && !$item->review;

        return $payload;
    }

    private function reviewPayload(?ProductReview $review): ?array
    {
        if (!$review) {
            return null;
        }

        return [
            'id' => $review->id,
            'rating' => $review->rating,
            'review' => $review->review,
            'variant_name' => $review->variant_name,
            'created_at' => $review->created_at,
        ];
    }

    private function storePayload($store): ?array
    {
        return $store ? [
            'id' => $store->id,
            'uuid' => $store->uuid,
            'store_name' => $store->store_name,
            'description' => $store->description,
            'banner' => $store->banner,
        ] : null;
    }

    private function notifyOrderPlaced(Collection $orders): void
    {
        foreach ($orders as $order) {
            if (!$order->store) {
                continue;
            }

            SendNotificationJob::dispatch(
                (int) $order->store->user_id,
                'order',
                'New Store Order',
                "A customer placed an order with {$order->items->count()} item(s) from your store.",
                [
                    'order_uuid' => $order->uuid,
                    'status' => 'pending',
                    'url' => $this->sellerOrderUrl($order),
                ]
            );
        }
    }

    private function notifyOrderCancelled(Order $order, string $reason): void
    {
        if (!$order->store) {
            return;
        }

        SendNotificationJob::dispatch(
            (int) $order->store->user_id,
            'order',
            'Store Order Cancelled',
            "The customer cancelled order {$order->uuid}. Reason: {$reason}",
            [
                'order_uuid' => $order->uuid,
                'status' => 'cancelled',
                'url' => $this->sellerOrderUrl($order),
            ]
        );
    }

    private function notifyOrderDelivered(Order $order): void
    {
        if (!$order->store) {
            return;
        }

        SendNotificationJob::dispatch(
            (int) $order->store->user_id,
            'order',
            'Store Order Delivered',
            "The customer confirmed delivery for order {$order->uuid}.",
            [
                'order_uuid' => $order->uuid,
                'status' => 'delivered',
                'url' => $this->sellerOrderUrl($order),
            ]
        );
    }

    private function sellerOrderUrl(Order $order): string
    {
        return "/seller/orders/{$order->uuid}";
    }
}
