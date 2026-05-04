<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreProductVariantRequest;
use App\Http\Requests\Seller\UpdateProductVariantRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SellerProductVariantController extends Controller
{
    private function syncProductStockStatus(Product $product): void
    {
        $hasAvailableStock = $product->variants()
            ->where('stock', '>', 0)
            ->exists();

        if (!$hasAvailableStock && $product->status === 'active') {
            $product->update(['status' => 'out_of_stock']);
            return;
        }

        if ($hasAvailableStock && $product->status === 'out_of_stock') {
            $product->update(['status' => 'active']);
        }
    }

    /**
     * GET /api/seller/products/{product_uuid}/variants
     * Get all variants for a product
     */
    public function index($productUuid, Request $request)
    {
        $user = $request->user();
        $product = Product::query()
            ->where('store_id', $user->store->id)
            ->where('uuid', $productUuid)
            ->firstOrFail();

        $variants = $product->variants;

        return response()->json([
            'data' => $variants,
            'count' => $variants->count(),
        ]);
    }

    /**
     * GET /api/seller/products/{product_uuid}/variants/{variant_id}
     * Get a specific variant
     */
    public function show($productUuid, $variantId, Request $request)
    {
        $user = $request->user();
        $product = Product::query()
            ->where('store_id', $user->store->id)
            ->where('uuid', $productUuid)
            ->firstOrFail();

        $variant = $product->variants()
            ->findOrFail($variantId);

        return response()->json(['data' => $variant]);
    }

    /**
     * POST /api/seller/products/{product_uuid}/variants
     * Create a new variant
     */
    public function store($productUuid, StoreProductVariantRequest $request)
    {
        $user = $request->user();
        $product = Product::query()
            ->where('store_id', $user->store->id)
            ->where('uuid', $productUuid)
            ->firstOrFail();

        $data = $request->validated();

        // Create variant
        $variant = $product->variants()->create([
            'name' => $data['name'],
            'price' => $data['price'],
            'stock' => $data['stock'],
        ]);

        $this->syncProductStockStatus($product->fresh());

        return response()->json([
            'message' => 'Variant created successfully.',
            'data' => $variant,
        ], 201);
    }

    /**
     * PUT /api/seller/products/{product_uuid}/variants/{variant_id}
     * Update a variant
     */
    public function update($productUuid, $variantId, UpdateProductVariantRequest $request)
    {
        $user = $request->user();
        $product = Product::query()
            ->where('store_id', $user->store->id)
            ->where('uuid', $productUuid)
            ->firstOrFail();

        $variant = $product->variants()
            ->findOrFail($variantId);

        $data = $request->validated();
        $variant->update([
            'name' => $data['name'],
            'price' => $data['price'],
            'stock' => $data['stock'],
        ]);

        $this->syncProductStockStatus($product->fresh());

        return response()->json([
            'message' => 'Variant updated successfully.',
            'data' => $variant,
        ]);
    }

    /**
     * DELETE /api/seller/products/{product_uuid}/variants/{variant_id}
     * Delete a variant
     */
    public function destroy($productUuid, $variantId, Request $request)
    {
        $user = $request->user();
        $product = Product::query()
            ->where('store_id', $user->store->id)
            ->where('uuid', $productUuid)
            ->firstOrFail();

        $variant = $product->variants()
            ->findOrFail($variantId);

        // Check if variant is in any cart
        if ($variant->cartItems()->exists()) {
            throw ValidationException::withMessages([
                'variant' => ['Cannot delete variant that is in customer carts'],
            ]);
        }

        $variant->delete();

        $this->syncProductStockStatus($product->fresh());

        return response()->json(['message' => 'Variant deleted successfully.']);
    }
}
