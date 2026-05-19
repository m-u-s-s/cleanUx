<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebhookEvent extends Model
{
    protected $fillable = [
        'event_id', 'event_code', 'payload', 'idempotency_key',
        'source_type', 'source_id', 'occurred_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
    ];

    public static function generateEventId(): string
    {
        return 'evt_' . Str::lower(Str::random(24));
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'event_id');
    }

    public function scopeForCode(Builder $q, string $eventCode): Builder
    {
        return $q->where('event_code', $eventCode);
    }
}
