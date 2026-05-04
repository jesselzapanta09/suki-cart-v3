<?php

namespace App\Http\Requests\Customer;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'location_id' => 'required|integer|exists:locations,id',
            'address_extra' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.cart_id' => 'nullable|integer|exists:carts,id',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.product_variant_id' => 'required|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.message' => 'nullable|string|max:1000',
        ];
    }

    public function messages()
    {
        return [
            'location_id.required' => 'Delivery location is required.',
            'location_id.exists' => 'The selected location does not exist.',
            'items.required' => 'Order must contain at least one item.',
            'items.*.product_id.required' => 'Product ID is required for each item.',
            'items.*.product_id.exists' => 'One or more selected products do not exist.',
            'items.*.product_variant_id.required' => 'Please select a variant for each item.',
            'items.*.product_variant_id.exists' => 'One or more selected variants do not exist.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }

    protected function withValidator($validator)
    {
        $validator->after(function ($validator) {
            foreach ($this->input('items', []) as $index => $item) {
                $productId = $item['product_id'] ?? null;
                $variantId = $item['product_variant_id'] ?? null;
                $quantity = (int) ($item['quantity'] ?? 0);

                if (!$productId || !$variantId || $quantity < 1) {
                    continue;
                }

                $product = Product::find($productId);

                if (!$product) {
                    continue;
                }

                if ($product->status !== 'active') {
                    $validator->errors()->add("items.{$index}.product_id", 'This product is not available for purchase.');
                    continue;
                }

                $variant = ProductVariant::find($variantId);

                if (!$variant || $variant->product_id !== (int) $productId) {
                    $validator->errors()->add("items.{$index}.product_variant_id", 'Invalid product variant.');
                    continue;
                }

                if ($variant->stock < $quantity) {
                    $validator->errors()->add("items.{$index}.quantity", "Not enough stock for {$variant->name}. Only {$variant->stock} available.");
                }
            }
        });
    }
}
