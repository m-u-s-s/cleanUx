<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MultiTradeBundleItem extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_QUOTED = 'quoted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'bundle_id', 'trade_id', 'label', 'description',
        'duration_minutes', 'estimated_price_cents', 'quoted_price_cents',
        'sequence_order', 'depends_on_item_ids',
        'assigned_provider_user_id', 'booking_id', 'status', 'metadata',
    ];

    protected $casts = [
        'depends_on_item_ids' => 'array',
        'metadata' => 'array',
        'duration_minutes' => 'integer',
        'estimated_price_cents' => 'integer',
        'quoted_price_cents' => 'integer',
        'sequence_order' => 'integer',
    ];

    /** @return BelongsTo<MultiTradeBundle, $this> */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(MultiTradeBundle::class, 'bundle_id');
    }

    /** @return BelongsTo<Trade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /** @return BelongsTo<User, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_provider_user_id');
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
