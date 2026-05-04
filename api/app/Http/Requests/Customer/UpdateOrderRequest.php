<?php

namespace App\Http\Requests\Customer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'status' => 'sometimes|in:pending,processing,shipped,delivered,cancelled',
            'cancelled_by' => 'required_if:status,cancelled|in:admin,seller,customer',
            'cancellation_reason' => 'required_if:status,cancelled|string|max:1000',
        ];
    }

    public function messages()
    {
        return [
            'status.in' => 'Invalid order status.',
            'cancelled_by.required_if' => 'Cancelled by is required when cancelling an order.',
            'cancelled_by.in' => 'Cancelled by must be admin, seller, or customer.',
            'cancellation_reason.required_if' => 'Cancellation reason is required when cancelling an order.',
        ];
    }
}
