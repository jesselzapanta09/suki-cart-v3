<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterSellerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firstname'          => ['required', 'string', 'max:255'],
            'lastname'           => ['required', 'string', 'max:255'],
            'contact_number'     => ['required', 'string', 'max:20'],
            'profile_picture'    => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'store_name'         => ['required', 'string', 'max:255'],
            'store_category'     => ['required', 'integer', 'exists:categories,id'],
            'store_description'  => ['required', 'string', 'max:500'],
            'store_banner'       => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'region'             => ['required', 'string', 'max:255'],
            'province'           => ['nullable', 'string', 'max:255'],
            'city'               => ['required', 'string', 'max:255'],
            'barangay'           => ['required', 'string', 'max:255'],
            'email'              => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'           => ['required', 'string', 'min:6'],
        ];
    }
}
