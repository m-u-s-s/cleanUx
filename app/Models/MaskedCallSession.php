<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MaskedCallSession extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'code', 'booking_id', 'client_user_id', 'provider_user_id',
        'twilio_session_sid', 'proxy_phone_number',
        'client_phone_masked', 'provider_phone_masked',
        'status', 'expires_at', 'closed_at',
        'calls_count', 'sms_count', 'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'closed_at' => 'datetime',
        'calls_count' => 'integer',
        'sms_count' => 'integer',
        'metadata' => 'array',
    ];

    public static function generateCode(): string
    {
        return 'mask_'.Str::lower(Str::random(20));
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}
