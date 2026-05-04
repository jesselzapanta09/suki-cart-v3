<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Seeder;

class ProductVariantSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()
            ->with('store')
            ->orderBy('store_id')
            ->orderBy('name')
            ->get();

        foreach ($products as $index => $product) {
            /** @var Product $product */
            $variantCount = ($index % 3) + 1;
            $basePrice = $this->basePriceFor($product->name, $index);
            $variantNames = array_slice($this->variantNamesFor($product->name), 0, $variantCount);

            foreach ($variantNames as $variantIndex => $variantName) {
                ProductVariant::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'name' => $variantName,
                    ],
                    [
                        'product_id' => $product->id,
                        'name' => $variantName,
                        'price' => $basePrice + ($variantIndex * 35),
                        'stock' => 12 + (($index + $variantIndex) % 9),
                    ]
                );
            }

            ProductVariant::query()
                ->where('product_id', $product->id)
                ->whereNotIn('name', $variantNames)
                ->delete();

            Product::query()
                ->whereKey($product->id)
                ->update(['status' => 'active']);
        }
    }

    private function basePriceFor(string $productName, int $index): float
    {
        $seed = crc32($productName) % 10;

        return (float) (99 + ($seed * 25) + (($index % 7) * 20));
    }

    private function variantNamesFor(string $productName): array
    {
        $lowerName = strtolower($productName);

        if (str_contains($lowerName, 'shirt') || str_contains($lowerName, 'jeans') || str_contains($lowerName, 'gloves')) {
            return ['Small', 'Medium', 'Large'];
        }

        if (str_contains($lowerName, 'sneakers')) {
            return ['Size 7', 'Size 8', 'Size 9'];
        }

        if (str_contains($lowerName, 'lip') || str_contains($lowerName, 'belt') || str_contains($lowerName, 'bag')) {
            return ['Classic', 'Neutral', 'Bold'];
        }

        if (str_contains($lowerName, 'cable')) {
            return ['1m White', '1m Black', '2m Black'];
        }

        if (str_contains($lowerName, 'charger') || str_contains($lowerName, 'earbuds') || str_contains($lowerName, 'speaker')) {
            return ['White', 'Black', 'Blue'];
        }

        if (str_contains($lowerName, 'diaper') || str_contains($lowerName, 'mask')) {
            return ['Small Pack', 'Medium Pack', 'Large Pack'];
        }

        if (str_contains($lowerName, 'rice') || str_contains($lowerName, 'food') || str_contains($lowerName, 'litter')) {
            return ['Regular', 'Family Pack', 'Value Bundle'];
        }

        return ['Regular', 'Twin Pack', 'Value Pack'];
    }
}
