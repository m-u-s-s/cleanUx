<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une course proposée à un prestataire. */
class AsapDispatchNotification extends Model
{
    protected $fillable = [
        'asap_dispatch_request_id', 'user_id', 'distance_m', 'radius_m',
        'notified_at', 'seen_at', 'declined_at', 'decline_reason', 'delivery_error',
    ];

    protected $casts = [
        'distance_m' => 'integer',
        'radius_m' => 'integer',
        'notified_at' => 'datetime',
        'seen_at' => 'datetime',
        'declined_at' => 'datetime',
    ];

    /** @return BelongsTo<AsapDispatchRequest, $this> */
    public function request(): BelongsTo
    {
        return $this->belongsTo(AsapDispatchRequest::class, 'asap_dispatch_request_id');
    }

    /** @return BelongsTo<User, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Les propositions encore vivantes pour ce prestataire : ni refusées, ni éteintes. */
    public function scopePending(Builder $q): Builder
    {
        return $q->whereNull('declined_at');
    }
}
