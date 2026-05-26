<?php

namespace App\Http\Requests\Api\Client;

use Illuminate\Foundation\Http\FormRequest;

class CreateTipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount_cents' => ['required', 'integer', 'min:100', 'max:50000'],
            'preset_label' => ['nullable', 'string', 'max:16'],
            'preset_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'message' => ['nullable', 'string', 'max:280'],
        ];
    }
}
