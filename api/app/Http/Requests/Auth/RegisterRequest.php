<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'string', 'max:20'],
            'profile_picture' => ['nullable', 'file', 'image', 'mimes:jpeg,png,jpg', 'max:5120'],
            'role' => ['required', 'in:admin,seller,customer'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }
}
