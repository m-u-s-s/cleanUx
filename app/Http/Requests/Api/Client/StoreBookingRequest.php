<?php

namespace App\Http\Requests\Api\Client;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // trade_id is optional: the trade is resolved via service_catalog when omitted,
            // but mobile clients should pass it to support the trade → service → options flow.
            'trade_id'           => ['nullable', 'integer', 'exists:trades,id'],
            'service_catalog_id' => ['required', 'integer', 'exists:service_catalogs,id'],
            'address'            => ['required', 'string', 'max:255'],
            'city'               => ['required', 'string', 'max:120'],
            'postal_code'        => ['required', 'string', 'max:20'],
            'country'            => ['nullable', 'string', 'size:2'],
            'scheduled_date'     => ['required', 'date', 'after_or_equal:today'],
            'scheduled_time'     => ['required', 'date_format:H:i'],
            'booking_mode'       => ['nullable', 'in:scheduled,asap'],
            'surface_m2'         => ['nullable', 'numeric', 'min:0'],
            'customer_comment'   => ['nullable', 'string', 'max:2000'],
            'priority'           => ['nullable', 'in:normal,urgent,low'],
            'contact_name'       => ['nullable', 'string', 'max:120'],
            'contact_phone'      => ['nullable', 'string', 'max:30'],
            'destination_lat'    => ['nullable', 'numeric', 'between:-90,90'],
            'destination_lng'    => ['nullable', 'numeric', 'between:-180,180'],
            // Answers from the trade-specific dynamic form (Trade.booking_form_schema).
            // Stored in Booking.trade_form_answers (JSON column).
            'trade_form_answers'         => ['nullable', 'array'],
            'trade_form_answers.*'       => ['nullable'],
        ];
    }
}
