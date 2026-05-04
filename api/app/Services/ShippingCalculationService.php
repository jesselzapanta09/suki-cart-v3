<?php

namespace App\Services;

use App\Models\Product;

class ShippingCalculationService
{
    private const BASE_SHIPPING_FEE = 100;
    private const WEIGHT_RATE = 50;

    /**
     * Calculate shipping per product order item.
     *
     * Formula: base fee + (product weight * quantity * weight rate).
     */
    public function calculateShipping(array $items): array
    {
        $productIds = array_unique(array_column($items, 'product_id'));
        $products = Product::whereIn('id', $productIds)
            ->with('store')
            ->get()
            ->keyBy('id');

        $totalShippingFee = 0;
        $breakdown = [];

        foreach ($items as $index => $item) {
            $product = $products->get($item['product_id']);

            if (!$product) {
                throw new \Exception("Product {$item['product_id']} not found");
            }

            $quantity = (int) ($item['quantity'] ?? 1);
            $unitWeight = (float) ($product->weight ?? 0);
            $totalWeight = $unitWeight * $quantity;
            $weightFee = $totalWeight * self::WEIGHT_RATE;
            $shippingFee = self::BASE_SHIPPING_FEE + $weightFee;

            $totalShippingFee += $shippingFee;

            $breakdown[] = [
                'index' => $index,
                'cart_id' => $item['cart_id'] ?? null,
                'product_id' => $product->id,
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'product_name' => $product->name,
                'store_id' => $product->store_id,
                'store_name' => $product->store->store_name ?? 'Unknown Store',
                'quantity' => $quantity,
                'unit_weight' => $unitWeight,
                'total_weight' => $totalWeight,
                'base_fee' => self::BASE_SHIPPING_FEE,
                'weight_rate' => self::WEIGHT_RATE,
                'weight_fee' => $weightFee,
                'shipping_fee' => $shippingFee,
            ];
        }

        return [
            'breakdown' => $breakdown,
            'total_shipping_fee' => $totalShippingFee,
        ];
    }
}
