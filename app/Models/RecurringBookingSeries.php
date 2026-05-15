<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringBookingSeries extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_CANCELLED = 'cancelled';



    protected $table = 'recurring_booking_series';

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'metadata' => 'array',
        'settings' => 'array',
        'days_of_week' => 'array',
        'days' => 'array',
        'template_payload' => 'array',
        'starts_at' => 'date',
        'ends_at' => 'date',
    ];
}
