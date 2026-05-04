<?php

namespace App\Http\Requests\Customer;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;

class StoreCartRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'product_id' => 'required|integer|exists:products,id',
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ];
    }

    public function messages()
    {
        return [
            'product_variant_id.required' => 'Please select a variant.',
            'product_variant_id.exists' => 'The selected variant does not exist.',
            'quantity.required' => 'Quantity is required.',
            'quantity.min' => 'Quantity must be at least 1.',
        ];
    }

    protected function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $productId = $this->input('product_id');
            $variantId = $this->input('product_variant_id');
            $quantity = $this->input('quantity');

            // Check product exists and is active
            $product = Product::find($productId);
            if (!$product) {
                $validator->errors()->add('product_id', 'Product not found.');
                return;
            }

            if ($product->status !== 'active') {
                $validator->errors()->add('product_id', 'This product is not available for purchase.');
                return;
            }

            // Variant is required - check variant exists and has stock
            $variant = ProductVariant::find($variantId);
            if (!$variant || $variant->product_id !== $productId) {
                $validator->errors()->add('product_variant_id', 'Invalid product variant.');
                return;
            }

            if ($variant->stock < $quantity) {
                $validator->errors()->add('quantity', "Not enough stock for this variant. Only {$variant->stock} available.");
            }
        });
    }
}
