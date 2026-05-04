<?php

namespace App\Http\Requests\Seller;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductVariantRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => 'nullable|string|max:255',
            'price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
        ];
    }

    public function messages()
    {
        return [
            'name.string' => 'Variant name must be a string',
            'price.numeric' => 'Price must be a number',
            'stock.integer' => 'Stock must be an integer',
        ];
    }
}
