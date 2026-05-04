<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'category_id' => 'sometimes|required|exists:categories,id',
            'weight' => 'sometimes|required|numeric|min:0.001|max:999999.9999',
            'dimension' => 'sometimes|required|string|max:255',
            'specs' => 'nullable|array',
            'status' => 'sometimes|required|in:active,draft,out_of_stock',
            'images' => 'sometimes|array|min:1|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:4096',
            'deleted_image_ids' => 'nullable|array',
            'deleted_image_ids.*' => 'integer',
        ];
    }

    protected function withValidator($validator)
    {
        // Validation logic moved to variant level since price/stock are now on variants
    }
}
