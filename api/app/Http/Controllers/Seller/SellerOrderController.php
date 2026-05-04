<?php

namespace App\Http\Controllers\Seller;

use App\Helpers\NotificationHelper;
use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SellerOrderController extends Controller
{
    public function index(Request $request)
    {
        $store = $this->sellerStore($request);
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 10), 50);

        $baseQuery = OrderItem::query()
            ->whereHas('product', fn ($q) => $q->where('store_id', $store->id));

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
            ->with(['user', 'location', 'product.images', 'product.store', 'variant'])
            ->orderByDesc('created_at');

        $items = $query->paginate($perPage);

        $groups = collect($items->items())
            ->groupBy('checkout_no')
            ->map(fn ($groupItems) => $this->decorateSellerGroup(collect($groupItems)))
            ->values();

        return response()->json([
            'message' => 'Seller order items retrieved successfully.',
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
        $store = $this->sellerStore($request);
        $item = $this->sellerCheckoutItem($checkoutNo, $store->id, ['user', 'location', 'product.images', 'product.store', 'variant']);

        $groupItems = OrderItem::query()
            ->where('checkout_no', $item->checkout_no)
            ->whereHas('product', fn ($q) => $q->where('store_id', $store->id))
            ->with(['user', 'location', 'product.images', 'product.store', 'variant'])
            ->orderBy('id')
            ->get();

        $group = $this->decorateSellerGroup($groupItems);
        $group['selected_item_id'] = $item->id;

        return response()->json([
            'message' => 'Seller order item retrieved successfully.',
            'data' => $group,
        ]);
    }

    public function updateStatus(Request $request, $itemId)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,shipped,cancelled',
            'courier_name' => 'nullable|required_if:status,shipped|string|max:255',
            'tracking_number' => 'nullable|required_if:status,shipped|string|max:255',
            'cancellation_reason' => 'nullable|required_if:status,cancelled|string|max:1000',
        ]);

        $store = $this->sellerStore($request);
        $item = $this->sellerItem($itemId, $store->id, ['product.store']);
        $previousStatus = $item->status;

        if (in_array($item->status, ['delivered', 'cancelled'])) {
            return response()->json([
                'message' => 'Cannot update item.',
                'error' => "This item is already {$item->status}.",
            ], 422);
        }

        if ($validated['status'] === 'cancelled' && in_array($item->status, ['shipped', 'delivered'])) {
            return response()->json([
                'message' => 'Cannot cancel item.',
                'error' => 'Shipped or delivered items cannot be cancelled.',
            ], 422);
        }

        DB::transaction(function () use ($item, $validated) {
            if ($validated['status'] === 'cancelled') {
                $this->restoreItemStock($item);
                $item->update([
                    'status' => 'cancelled',
                    'cancelled_by' => 'seller',
                    'cancellation_reason' => $validated['cancellation_reason'],
                    'cancelled_at' => now(),
                ]);

                return;
            }

            $item->update([
                'status' => $validated['status'],
                'courier_name' => $validated['status'] === 'shipped' ? $validated['courier_name'] : $item->courier_name,
                'tracking_number' => $validated['status'] === 'shipped' ? $validated['tracking_number'] : $item->tracking_number,
            ]);
        });

        if ($validated['status'] !== $previousStatus) {
            $this->notifyCustomerStatusChanged($item->fresh(['product.store']), $store, $validated);
        }

        return $this->freshSellerGroupResponse($item->checkout_no, $store->id, $item->id, 'Order item updated successfully.');
    }

    public function updateShipment(Request $request, $itemId)
    {
        $validated = $request->validate([
            'courier_name' => 'required|string|max:255',
            'tracking_number' => 'required|string|max:255',
        ]);

        $store = $this->sellerStore($request);
        $item = $this->sellerItem($itemId, $store->id, ['product.store']);

        $item->update([
            'courier_name' => $validated['courier_name'],
            'tracking_number' => $validated['tracking_number'],
        ]);

        $this->notifyCustomerShipmentUpdated($item->fresh(['product.store']), $store);

        return $this->freshSellerGroupResponse($item->checkout_no, $store->id, $item->id, 'Courier details saved successfully.');
    }

    public function cancelItem(Request $request, $itemId)
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        $store = $this->sellerStore($request);
        $item = $this->sellerItem($itemId, $store->id, ['product.store']);

        if (in_array($item->status, ['cancelled', 'shipped', 'delivered'])) {
            return response()->json([
                'message' => 'Cannot cancel item.',
                'error' => "This item cannot be cancelled once it is {$item->status}.",
            ], 422);
        }

        DB::transaction(function () use ($item, $validated) {
            $this->restoreItemStock($item);
            $item->update([
                'status' => 'cancelled',
                'cancelled_by' => 'seller',
                'cancellation_reason' => $validated['cancellation_reason'],
                'cancelled_at' => now(),
            ]);
        });

        $this->notifyCustomerStatusChanged($item->fresh(['product.store']), $store, [
            'status' => 'cancelled',
            'cancellation_reason' => $validated['cancellation_reason'],
        ]);

        return $this->freshSellerGroupResponse($item->checkout_no, $store->id, $item->id, 'Order item cancelled successfully.');
    }

    private function sellerStore(Request $request)
    {
        $store = $request->user()->store;

        if (!$store) {
            abort(response()->json(['message' => 'Store not found.'], 404));
        }

        return $store;
    }

    private function sellerItem($itemId, int $storeId, array $with = []): OrderItem
    {
        return OrderItem::query()
            ->where('id', $itemId)
            ->whereHas('product', fn ($q) => $q->where('store_id', $storeId))
            ->with($with)
            ->firstOrFail();
    }

    private function sellerCheckoutItem(string $checkoutNo, int $storeId, array $with = []): OrderItem
    {
        /** @var OrderItem $item */
        $item = OrderItem::query()
            ->where('checkout_no', $checkoutNo)
            ->whereHas('product', fn ($q) => $q->where('store_id', $storeId))
            ->with($with)
            ->orderBy('id')
            ->firstOrFail();

        return $item;
    }

    private function freshSellerGroupResponse(string $checkoutNo, int $storeId, int $selectedItemId, string $message)
    {
        $items = OrderItem::query()
            ->where('checkout_no', $checkoutNo)
            ->whereHas('product', fn ($q) => $q->where('store_id', $storeId))
            ->with(['user', 'location', 'product.images', 'product.store', 'variant'])
            ->orderBy('id')
            ->get();

        $group = $this->decorateSellerGroup($items);
        $group['selected_item_id'] = $selectedItemId;

        return response()->json([
            'message' => $message,
            'data' => $group,
        ]);
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

    private function decorateSellerGroup(Collection $items): array
    {
        $first = $items->first();
        $customer = $first?->user;
        $store = $first?->product?->store;
        $activeItems = $items->where('status', '!=', 'cancelled');

        $decoratedItems = $items->values()->map(function ($item) {
            $payload = $item->toArray();
            $payload['line_total'] = (float) $item->price * $item->quantity;
            $payload['item_total'] = $item->status === 'cancelled' ? 0 : $payload['line_total'] + (float) $item->shipping_cost;

            return $payload;
        })->toArray();

        return [
            'id' => $first?->checkout_no,
            'checkout_no' => $first?->checkout_no,
            'created_at' => $first?->created_at,
            'location' => $first?->location,
            'address_extra' => $first?->address_extra,
            'message' => $first?->message,
            'customer' => $customer ? [
                'id' => $customer->id,
                'uuid' => $customer->uuid,
                'firstname' => $customer->firstname,
                'lastname' => $customer->lastname,
                'email' => $customer->email,
                'contact_number' => $customer->contact_number,
                'profile_picture' => $customer->profile_picture,
            ] : null,
            'store_order' => [
                'store' => $store ? [
                    'id' => $store->id,
                    'uuid' => $store->uuid,
                    'store_name' => $store->store_name,
                ] : null,
                'items' => $decoratedItems,
                'active_items_count' => $activeItems->count(),
                'cancelled_items_count' => $items->where('status', 'cancelled')->count(),
                'subtotal' => $activeItems->sum(fn ($item) => (float) $item->price * $item->quantity),
                'shipping_cost' => $activeItems->sum(fn ($item) => (float) $item->shipping_cost),
                'status' => $this->sellerStatus($items),
            ],
        ];
    }

    private function sellerStatus(Collection $items): string
    {
        $active = $items->where('status', '!=', 'cancelled');

        if ($active->isEmpty()) {
            return 'cancelled';
        }

        $statuses = $active->pluck('status');

        if ($statuses->contains('pending')) {
            return 'pending';
        }

        if ($statuses->contains('processing')) {
            return 'processing';
        }

        if ($statuses->contains('shipped')) {
            return 'shipped';
        }

        return $active->first()?->status ?? 'pending';
    }

    private function notifyCustomerStatusChanged(OrderItem $item, $store, array $validated): void
    {
        $status = $validated['status'];
        $statusLabel = $this->statusLabel($status);
        $message = "{$store->store_name} marked {$item->product?->name} as {$statusLabel}.";

        if ($status === 'shipped') {
            $message .= " Courier: {$validated['courier_name']}. Tracking: {$validated['tracking_number']}.";
        }

        if ($status === 'cancelled' && !empty($validated['cancellation_reason'])) {
            $message .= " Reason: {$validated['cancellation_reason']}.";
        }

        NotificationHelper::send(
            userId: $item->user_id,
            type: 'order',
            title: $this->statusTitle($status),
            message: $message,
            data: [
                'checkout_no' => $item->checkout_no,
                'order_item_id' => $item->id,
                'status' => $status,
                'status_label' => $statusLabel,
                'url' => $this->customerOrderUrl($item),
            ],
        );
    }

    private function notifyCustomerShipmentUpdated(OrderItem $item, $store): void
    {
        NotificationHelper::send(
            userId: $item->user_id,
            type: 'order',
            title: 'Courier Details Updated',
            message: "{$store->store_name} updated courier details for {$item->product?->name}. Courier: {$item->courier_name}. Tracking: {$item->tracking_number}.",
            data: [
                'checkout_no' => $item->checkout_no,
                'order_item_id' => $item->id,
                'status' => $item->status,
                'status_label' => $this->statusLabel($item->status),
                'url' => $this->customerOrderUrl($item),
            ],
        );
    }

    private function customerOrderUrl(OrderItem $item): string
    {
        return "/customer/orders/items/{$item->checkout_no}";
    }

    private function statusTitle(string $status): string
    {
        return [
            'processing' => 'Product Order Processing',
            'shipped' => 'Product Order Shipped',
            'delivered' => 'Product Order Delivered',
            'cancelled' => 'Product Order Cancelled',
            'pending' => 'Product Order Updated',
        ][$status] ?? 'Product Order Updated';
    }

    private function statusLabel(string $status): string
    {
        return [
            'pending' => 'Order placed',
            'processing' => 'Preparing to ship',
            'shipped' => 'Shipped out',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ][$status] ?? ucfirst($status);
    }
}
