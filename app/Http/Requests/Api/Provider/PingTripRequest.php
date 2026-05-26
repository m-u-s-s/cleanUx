<?php

namespace App\Http\Requests\Api\Provider;

use Illuminate\Foundation\Http\FormRequest;

class PingTripRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'speed_mps' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'heading_deg' => ['nullable', 'numeric', 'between:0,360'],
            'sequence' => ['nullable', 'string', 'max:64'],
        ];
    }
}
