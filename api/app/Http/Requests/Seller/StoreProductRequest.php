<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'weight' => 'required|numeric|min:0.001|max:999999.9999',
            'dimension' => 'required|string|max:255',
            'specs' => 'nullable|array',
            'status' => 'required|in:active,draft,out_of_stock',
            'images' => 'required|array|min:1|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:4096',
        ];
    }

    protected function withValidator($validator)
    {
        // Validation logic moved to variant level since price/stock are now on variants
    }
}
