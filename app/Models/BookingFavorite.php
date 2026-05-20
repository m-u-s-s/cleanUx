<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingFavorite extends Model
{
    protected $fillable = [
        'client_user_id', 'label', 'source_booking_id',
        'preferred_provider_user_id', 'trade_id', 'service_zone_id',
        'snapshot', 'use_count', 'last_used_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'use_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_provider_user_id');
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function sourceBooking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'source_booking_id');
    }
}
