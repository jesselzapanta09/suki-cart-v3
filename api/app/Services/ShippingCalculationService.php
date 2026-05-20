<?php

namespace App\Services;

use App\Models\Product;

class ShippingCalculationService
{
    private const BASE_SHIPPING_FEE = 100;
    private const WEIGHT_RATE = 50;

    /**
     * Calculate shipping per store order.
     *
     * Formula per store: base fee + (combined product weight * weight rate).
     */
    public function calculateShipping(array $items): array
    {
        $productIds = array_unique(array_column($items, 'product_id'));
        $products = Product::whereIn('id', $productIds)
            ->with('store')
            ->get()
            ->keyBy('id');

        $grouped = [];

        foreach ($items as $index => $item) {
            $product = $products->get($item['product_id']);

            if (!$product) {
                throw new \Exception("Product {$item['product_id']} not found");
            }

            $quantity = (int) ($item['quantity'] ?? 1);
            $unitWeight = (float) ($product->weight ?? 0);
            $totalWeight = $unitWeight * $quantity;
            $storeKey = (string) $product->store_id;

            if (!isset($grouped[$storeKey])) {
                $grouped[$storeKey] = [
                    'store_id' => $product->store_id,
                    'store_name' => $product->store->store_name ?? 'Unknown Store',
                    'base_fee' => self::BASE_SHIPPING_FEE,
                    'weight_rate' => self::WEIGHT_RATE,
                    'total_weight' => 0,
                    'items' => [],
                ];
            }

            $grouped[$storeKey]['total_weight'] += $totalWeight;
            $grouped[$storeKey]['items'][] = [
                'index' => $index,
                'cart_id' => $item['cart_id'] ?? null,
                'product_id' => $product->id,
                'product_variant_id' => $item['product_variant_id'] ?? null,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_weight' => $unitWeight,
                'total_weight' => $totalWeight,
            ];
        }

        $totalShippingFee = 0;
        $breakdown = collect($grouped)
            ->values()
            ->map(function (array $group) use (&$totalShippingFee) {
                $weightFee = $group['total_weight'] * self::WEIGHT_RATE;
                $shippingFee = self::BASE_SHIPPING_FEE + $weightFee;
                $totalShippingFee += $shippingFee;

                $group['weight_fee'] = $weightFee;
                $group['shipping_fee'] = $shippingFee;

                return $group;
            })
            ->values()
            ->all();

        return [
            'breakdown' => $breakdown,
            'total_shipping_fee' => $totalShippingFee,
        ];
    }
}
