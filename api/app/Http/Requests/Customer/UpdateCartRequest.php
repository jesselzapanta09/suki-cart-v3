<?php

namespace App\Http\Requests\Customer;

use App\Models\Cart;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'quantity' => 'required|integer|min:1',
        ];
    }

    protected function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $cartId = $this->route('id');
            $quantity = $this->input('quantity');

            // Get cart item and variant/product
            $cartItem = Cart::find($cartId);
            if (!$cartItem) {
                $validator->errors()->add('id', 'Cart item not found.');
                return;
            }

            // Check variant stock if variant is associated
            if ($cartItem->product_variant_id && $cartItem->variant) {
                $variant = $cartItem->variant;
                if ($variant->stock < $quantity) {
                    $validator->errors()->add('quantity', "Not enough stock for this variant. Only {$variant->stock} available.");
                }
            } else {
                // If no variant, product should have variants (stock is now on variants)
                $product = $cartItem->product;
                if (!$product) {
                    $validator->errors()->add('product', 'Product not found.');
                    return;
                }
                
                // For products without a specific variant, check if any variant has enough stock
                if ($product->variants && $product->variants->count() > 0) {
                    $totalStock = $product->variants->sum('stock');
                    if ($totalStock < $quantity) {
                        $validator->errors()->add('quantity', "Not enough stock available. Only {$totalStock} total available across variants.");
                    }
                } else {
                    $validator->errors()->add('quantity', 'This product has no available variants.');
                }
            }
        });
    }
}
