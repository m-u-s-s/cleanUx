<?php

namespace App\Http\Requests\Api\Auth;

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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:30'],
            'locale' => ['nullable', 'string', 'in:fr,nl,en'],
            'accept_terms' => ['required', 'accepted'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'referral_code' => ['nullable', 'string', 'max:64'],
        ];
    }
}
