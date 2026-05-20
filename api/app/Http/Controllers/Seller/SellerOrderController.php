<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Jobs\SendNotificationJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SellerOrderController extends Controller
{
    public function index(Request $request)
    {
        $store = $this->sellerStore($request);
        $status = $request->query('status');
        $search = trim((string) $request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 10), 50);

        $baseQuery = Order::query()
            ->where('store_id', $store->id);

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
            ->with($this->sellerOrderRelations())
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
            'message' => 'Seller orders retrieved successfully.',
            'data' => collect($orders->items())->map(fn (Order $order) => $this->decorateSellerOrder($order))->values(),
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
        $store = $this->sellerStore($request);
        $order = $this->sellerOrder($uuid, $store->id, $this->sellerOrderRelations());

        return response()->json([
            'message' => 'Seller order retrieved successfully.',
            'data' => $this->decorateSellerOrder($order),
        ]);
    }

    public function updateStatus(Request $request, string $uuid)
    {
        $validated = $request->validate([
            'status' => 'required|in:processing,shipped',
            'courier_name' => 'nullable|required_if:status,shipped|string|max:255',
            'tracking_number' => 'nullable|required_if:status,shipped|string|max:255',
        ]);

        $store = $this->sellerStore($request);
        $order = $this->sellerOrder($uuid, $store->id, $this->sellerOrderRelations());

        if (in_array($order->status, ['delivered', 'cancelled'], true)) {
            return response()->json([
                'message' => 'Cannot update order.',
                'error' => "This order is already {$order->status}.",
            ], 422);
        }

        $nextStatusMap = [
            'pending' => 'processing',
            'processing' => 'shipped',
        ];

        $expectedNextStatus = $nextStatusMap[$order->status] ?? null;
        if ($expectedNextStatus !== $validated['status']) {
            return response()->json([
                'message' => 'Cannot update order.',
                'error' => 'Orders must be updated in sequence.',
            ], 422);
        }

        $previousStatus = $order->status;

        $order->update([
            'status' => $validated['status'],
            'courier_name' => $validated['status'] === 'shipped' ? $validated['courier_name'] : $order->courier_name,
            'tracking_number' => $validated['status'] === 'shipped' ? $validated['tracking_number'] : $order->tracking_number,
            'shipped_at' => $validated['status'] === 'shipped' ? now() : $order->shipped_at,
        ]);

        $order->refresh()->load($this->sellerOrderRelations());

        if ($validated['status'] !== $previousStatus) {
            $this->notifyCustomerStatusChanged($order, $store, $validated['status']);
        }

        return response()->json([
            'message' => 'Order updated successfully.',
            'data' => $this->decorateSellerOrder($order),
        ]);
    }

    public function updateShipment(Request $request, string $uuid)
    {
        $validated = $request->validate([
            'courier_name' => 'required|string|max:255',
            'tracking_number' => 'required|string|max:255',
        ]);

        $store = $this->sellerStore($request);
        $order = $this->sellerOrder($uuid, $store->id, $this->sellerOrderRelations());

        if ($order->status !== 'shipped') {
            return response()->json([
                'message' => 'Cannot update shipment.',
                'error' => 'Shipment details can only be updated after the order is marked as shipped.',
            ], 422);
        }

        $order->update([
            'courier_name' => $validated['courier_name'],
            'tracking_number' => $validated['tracking_number'],
        ]);

        $order->refresh()->load($this->sellerOrderRelations());
        $this->notifyCustomerShipmentUpdated($order, $store);

        return response()->json([
            'message' => 'Courier details saved successfully.',
            'data' => $this->decorateSellerOrder($order),
        ]);
    }

    public function cancelOrder(Request $request, string $uuid)
    {
        $validated = $request->validate([
            'cancellation_reason' => 'required|string|max:1000',
        ]);

        $store = $this->sellerStore($request);
        $order = $this->sellerOrder($uuid, $store->id, $this->sellerOrderRelations());

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
                'cancelled_by' => 'seller',
                'cancellation_reason' => $validated['cancellation_reason'],
                'cancelled_at' => now(),
            ]);
        });

        $order->refresh()->load($this->sellerOrderRelations());
        $this->notifyCustomerStatusChanged($order, $store, 'cancelled');

        return response()->json([
            'message' => 'Order cancelled successfully.',
            'data' => $this->decorateSellerOrder($order),
        ]);
    }

    private function sellerStore(Request $request)
    {
        $store = $request->user()->store;

        if (!$store) {
            abort(response()->json(['message' => 'Store not found.'], 404));
        }

        return $store;
    }

    private function sellerOrder(string $uuid, int $storeId, array $with = []): Order
    {
        return Order::query()
            ->where('store_id', $storeId)
            ->where('uuid', $uuid)
            ->with($with)
            ->firstOrFail();
    }

    private function sellerOrderRelations(): array
    {
        return [
            'user',
            'location',
            'store',
            'items.product.images',
            'items.variant',
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

    private function decorateSellerOrder(Order $order): array
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
            'customer' => $order->user ? [
                'id' => $order->user->id,
                'uuid' => $order->user->uuid,
                'firstname' => $order->user->firstname,
                'lastname' => $order->user->lastname,
                'email' => $order->user->email,
                'contact_number' => $order->user->contact_number,
                'profile_picture' => $order->user->profile_picture,
            ] : null,
            'store' => $order->store ? [
                'id' => $order->store->id,
                'uuid' => $order->store->uuid,
                'store_name' => $order->store->store_name,
            ] : null,
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
            'order_items' => $items->map(function (OrderItem $item) use ($order) {
                $payload = $item->toArray();
                $payload['line_total'] = (float) $item->price * $item->quantity;
                $payload['status'] = $order->status;

                return $payload;
            })->values()->all(),
        ];
    }

    private function notifyCustomerStatusChanged(Order $order, $store, string $status): void
    {
        $statusLabel = $this->statusLabel($status);
        $message = "{$store->store_name} marked order {$order->uuid} as {$statusLabel}.";

        if ($status === 'shipped') {
            $message .= " Courier: {$order->courier_name}. Tracking: {$order->tracking_number}.";
        }

        if ($status === 'cancelled' && !empty($order->cancellation_reason)) {
            $message .= " Reason: {$order->cancellation_reason}.";
        }

        SendNotificationJob::dispatch(
            (int) $order->user_id,
            'order',
            $this->statusTitle($status),
            $message,
            [
                'order_uuid' => $order->uuid,
                'status' => $status,
                'status_label' => $statusLabel,
                'url' => $this->customerOrderUrl($order),
            ]
        );
    }

    private function notifyCustomerShipmentUpdated(Order $order, $store): void
    {
        SendNotificationJob::dispatch(
            (int) $order->user_id,
            'order',
            'Courier Details Updated',
            "{$store->store_name} updated courier details for order {$order->uuid}. Courier: {$order->courier_name}. Tracking: {$order->tracking_number}.",
            [
                'order_uuid' => $order->uuid,
                'status' => $order->status,
                'status_label' => $this->statusLabel($order->status),
                'url' => $this->customerOrderUrl($order),
            ]
        );
    }

    private function customerOrderUrl(Order $order): string
    {
        return "/customer/orders/{$order->uuid}";
    }

    private function statusTitle(string $status): string
    {
        return [
            'processing' => 'Store Order Processing',
            'shipped' => 'Store Order Shipped',
            'delivered' => 'Store Order Delivered',
            'cancelled' => 'Store Order Cancelled',
            'pending' => 'Store Order Updated',
        ][$status] ?? 'Store Order Updated';
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
