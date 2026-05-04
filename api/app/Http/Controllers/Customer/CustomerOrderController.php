<?php

namespace App\Http\Controllers\Customer;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreOrderRequest;
use App\Models\Cart;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductReview;
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

        $baseQuery = OrderItem::query()
            ->where('user_id', $user->id);

        $filteredBaseQuery = (clone $baseQuery)
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('checkout_no', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($productQuery) => $productQuery->where('name', 'like', "%{$search}%"));
                });
            });

        $statusCounts = (clone $filteredBaseQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $query = (clone $filteredBaseQuery)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['location', 'product.images', 'product.store', 'variant', 'review'])
            ->orderByDesc('created_at');

        $items = $query->paginate($perPage);

        $groups = collect($items->items())
            ->map(fn ($item) => $this->decorateCheckoutGroup(collect([$item])))
            ->values();

        return response()->json([
            'message' => 'Order items retrieved successfully.',
            'data' => $groups,
            'pagination' => [
                'total' => $items->total(),
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
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

    public function show(Request $request, string $checkoutNo)
    {
        $item = $this->customerCheckoutItem($request, $checkoutNo, ['location', 'product.images', 'product.store', 'variant', 'review']);
        $group = $this->groupSnapshot($request->user()->id, $item->checkout_no);

        return response()->json([
            'message' => 'Order item retrieved successfully.',
            'data' => [
                'item' => $this->decorateItem($item),
                'group' => $group,
            ],
        ]);
    }

    public function store(StoreOrderRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();
        $user->locations()->findOrFail($data['location_id']);

        $items = $data['items'];
        $shippingResult = (new ShippingCalculationService())->calculateShipping($items);
        $shippingByIndex = collect($shippingResult['breakdown'])->keyBy('index');

        try {
            $createdIds = DB::transaction(function () use ($user, $data, $items, $shippingByIndex) {
                $createdIds = [];

                foreach ($items as $index => $item) {
                    $variant = ProductVariant::find($item['product_variant_id']);

                    if (!$variant) {
                        throw ValidationException::withMessages([
                            "items.{$index}.product_variant_id" => 'Please select a valid variant.',
                        ]);
                    }

                    if ($variant->stock < $item['quantity']) {
                        throw ValidationException::withMessages([
                            "items.{$index}.quantity" => "Only {$variant->stock} items available for {$variant->name}.",
                        ]);
                    }

                    $orderItem = OrderItem::create([
                        'checkout_no' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'location_id' => $data['location_id'],
                        'address_extra' => $data['address_extra'] ?? null,
                        'message' => $item['message'] ?? null,
                        'product_id' => $item['product_id'],
                        'product_variant_id' => $item['product_variant_id'],
                        'quantity' => $item['quantity'],
                        'price' => $variant->price,
                        'shipping_cost' => $shippingByIndex->get($index)['shipping_fee'] ?? 0,
                        'status' => 'pending',
                    ]);

                    $createdIds[] = $orderItem->id;
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

                return $createdIds;
            });

            $createdItems = OrderItem::query()
                ->whereIn('id', $createdIds)
                ->with(['location', 'product.images', 'product.store', 'variant', 'review'])
                ->orderBy('id')
                ->get();

            $this->notifyOrderPlaced($createdItems);

            return response()->json([
                'message' => 'Order items created successfully.',
                'data' => [
                    'checkout_no' => $createdItems->first()?->checkout_no,
                    'first_item_id' => $createdItems->first()?->id,
                    'group' => $this->decorateCheckoutGroup(collect([$createdItems->first()])),
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Please review your order items.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create order items.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function cancelItem(Request $request, $itemId)
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        $item = $this->customerItem($request, (int) $itemId);

        if (!in_array($item->status, ['pending', 'processing'])) {
            return response()->json([
                'message' => 'Cannot cancel item.',
                'error' => "This item cannot be cancelled once it is {$item->status}.",
            ], 422);
        }

        DB::transaction(function () use ($item, $validated) {
            $this->restoreItemStock($item);
            $item->update([
                'status' => 'cancelled',
                'cancelled_by' => 'customer',
                'cancellation_reason' => $validated['cancellation_reason'],
                'cancelled_at' => now(),
            ]);
        });

        $item->load(['location', 'product.images', 'product.store', 'variant', 'review']);
        $this->notifyItemCancelled($item, $validated['cancellation_reason']);

        return response()->json([
            'message' => 'Order item cancelled successfully.',
            'data' => [
                'item' => $this->decorateItem($item),
                'group' => $this->groupSnapshot($request->user()->id, $item->checkout_no),
            ],
        ]);
    }

    public function deliverItem(Request $request, $itemId)
    {
        $item = $this->customerItem($request, (int) $itemId);

        if ($item->status !== 'shipped') {
            return response()->json([
                'message' => 'Cannot mark item delivered.',
                'error' => 'Only shipped items can be marked as delivered.',
            ], 422);
        }

        $item->update(['status' => 'delivered']);
        $item->load(['location', 'product.images', 'product.store', 'variant', 'review']);

        $this->notifyItemDelivered($item);

        return response()->json([
            'message' => 'Order item marked as delivered.',
            'data' => [
                'item' => $this->decorateItem($item),
                'group' => $this->groupSnapshot($request->user()->id, $item->checkout_no),
            ],
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

    private function customerItem(Request $request, int $itemId, array $with = []): OrderItem
    {
        return OrderItem::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $itemId)
            ->with($with)
            ->firstOrFail();
    }

    private function customerCheckoutItem(Request $request, string $checkoutNo, array $with = []): OrderItem
    {
        /** @var OrderItem $item */
        $item = OrderItem::query()
            ->where('user_id', $request->user()->id)
            ->where('checkout_no', $checkoutNo)
            ->with($with)
            ->orderBy('id')
            ->firstOrFail();

        return $item;
    }

    private function groupSnapshot(int $userId, string $checkoutNo): array
    {
        $items = OrderItem::query()
            ->where('user_id', $userId)
            ->where('checkout_no', $checkoutNo)
            ->with(['location', 'product.images', 'product.store', 'variant', 'review'])
            ->orderBy('id')
            ->get();

        return $this->decorateCheckoutGroup($items);
    }

    private function restoreItemStock(OrderItem $item): void
    {
        $variant = ProductVariant::find($item->product_variant_id);

        if (!$variant) {
            return;
        }

        $variant->increment('stock', $item->quantity);
        $this->syncProductStockStatus($variant->product_id);
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

    private function decorateCheckoutGroup(Collection $items): array
    {
        $first = $items->first();
        $activeItems = $items->where('status', '!=', 'cancelled')->values();
        $subtotal = $activeItems->sum(fn ($item) => (float) $item->price * $item->quantity);
        $shippingCost = $activeItems->sum(fn ($item) => (float) $item->shipping_cost);

        return [
            'id' => $first?->checkout_no,
            'checkout_no' => $first?->checkout_no,
            'created_at' => $first?->created_at,
            'location' => $first?->location,
            'address_extra' => $first?->address_extra,
            'message' => $first?->message,
            'active_items_count' => $activeItems->count(),
            'cancelled_items_count' => $items->where('status', 'cancelled')->count(),
            'price' => $subtotal,
            'shipping_cost' => $shippingCost,
            'total_price' => $subtotal + $shippingCost,
            'order_items' => $items->values()->map(fn ($item) => $this->decorateItem($item))->toArray(),
            'item_groups' => $items->values()->map(fn ($item) => [
                'store' => $this->storePayload($item->product?->store),
                'status' => $item->status,
                'shipment' => $item->courier_name ? [
                    'courier_name' => $item->courier_name,
                    'tracking_number' => $item->tracking_number,
                ] : null,
                'items' => [$this->decorateItem($item)],
                'subtotal' => $item->status === 'cancelled' ? 0 : (float) $item->price * $item->quantity,
            ])->toArray(),
        ];
    }

    private function decorateItem(OrderItem $item): array
    {
        $payload = $item->toArray();
        $payload['line_total'] = (float) $item->price * $item->quantity;
        $payload['item_total'] = $item->status === 'cancelled' ? 0 : $payload['line_total'] + (float) $item->shipping_cost;
        $payload['store'] = $this->storePayload($item->product?->store);
        $payload['review'] = $this->reviewPayload($item->review);
        $payload['can_review'] = $item->status === 'delivered' && !$item->review;

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

    private function notifyOrderPlaced(Collection $items): void
    {
        foreach ($items as $item) {
            $store = $item->product?->store;
            if ($store) {
                NotificationHelper::send(
                    userId: $store->user_id,
                    type: 'order',
                    title: 'New Product Order',
                    message: "{$item->product?->name} was ordered by a customer.",
                    data: [
                        'checkout_no' => $item->checkout_no,
                        'order_item_id' => $item->id,
                        'status' => 'pending',
                        'url' => $this->sellerOrderUrl($item),
                    ],
                );
            }
        }
    }

    private function notifyItemCancelled(OrderItem $item, string $reason): void
    {
        $store = $item->product?->store;
        if ($store) {
            NotificationHelper::send(
                userId: $store->user_id,
                type: 'order',
                title: 'Product Order Cancelled',
                message: "The customer cancelled {$item->product?->name}. Reason: {$reason}",
                data: [
                    'checkout_no' => $item->checkout_no,
                    'order_item_id' => $item->id,
                    'status' => 'cancelled',
                    'url' => $this->sellerOrderUrl($item),
                ],
            );
        }
    }

    private function notifyItemDelivered(OrderItem $item): void
    {
        $store = $item->product?->store;

        if ($store) {
            NotificationHelper::send(
                userId: $store->user_id,
                type: 'order',
                title: 'Product Delivered',
                message: "The customer confirmed delivery for {$item->product?->name}.",
                data: [
                    'checkout_no' => $item->checkout_no,
                    'order_item_id' => $item->id,
                    'status' => 'delivered',
                    'url' => $this->sellerOrderUrl($item),
                ],
            );
        }
    }

    private function sellerOrderUrl(OrderItem $item): string
    {
        return "/seller/orders/items/{$item->checkout_no}";
    }
}
