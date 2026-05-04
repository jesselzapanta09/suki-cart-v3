<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'firstname'         => ['required', 'string', 'max:255'],
            'lastname'          => ['required', 'string', 'max:255'],
            'email'             => ['required', 'email', 'unique:users,email,' . $userId],
            'role'              => ['required', 'in:admin,seller,customer'],
            'contact_number'    => ['nullable', 'string', 'max:20'],
            'password'          => ['nullable', 'string', 'min:6'],
            'profile_picture'   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_picture'    => ['nullable', 'boolean'],
            // Address
            'region'            => ['nullable', 'string', 'max:255'],
            'province'          => ['nullable', 'string', 'max:255'],
            'city'              => ['nullable', 'string', 'max:255'],
            'barangay'          => ['nullable', 'string', 'max:255'],
            // Store (seller only)
            'store_name'        => ['required_if:role,seller', 'nullable', 'string', 'max:255'],
            'store_category'    => ['required_if:role,seller', 'nullable', 'string', 'max:255'],
            'store_description' => ['nullable', 'string', 'max:500'],
            'store_banner'      => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
