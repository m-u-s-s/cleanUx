<?php

namespace App\Http\Requests\Api\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'punctuality' => ['nullable', 'integer', 'min:1', 'max:5'],
            'quality' => ['nullable', 'integer', 'min:1', 'max:5'],
            'communication' => ['nullable', 'integer', 'min:1', 'max:5'],
            'value' => ['nullable', 'integer', 'min:1', 'max:5'],
            'is_public' => ['boolean'],
        ];
    }
}
