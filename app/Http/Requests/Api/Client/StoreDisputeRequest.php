<?php

namespace App\Http\Requests\Api\Client;

use App\Support\Disputes\PreuvesDeLitige;
use Illuminate\Foundation\Http\FormRequest;

class StoreDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:3', 'max:120'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'category' => ['required', 'in:quality,no_show,payment,damage,safety,communication,other'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'severity' => ['nullable', 'in:low,medium,high,critical'],
            'booking_id' => ['nullable', 'integer'],
        ] + PreuvesDeLitige::regles('attachments');
    }
}
