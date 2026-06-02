<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MultiTradeBundle extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_QUOTING = 'quoting';

    public const STATUS_QUOTED = 'quoted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'code', 'name', 'description', 'client_user_id', 'status',
        'total_estimated_cents', 'total_quoted_cents', 'total_final_cents', 'currency',
        'bundle_discount_percent', 'bundle_discount_cents',
        'address', 'preferred_start_date', 'preferred_end_date',
        'metadata', 'accepted_at', 'completed_at', 'cancelled_at',
    ];

    protected $casts = [
        'address' => 'array',
        'metadata' => 'array',
        'preferred_start_date' => 'date',
        'preferred_end_date' => 'date',
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'bundle_discount_percent' => 'float',
    ];

    public static function generateCode(): string
    {
        return 'bndl_'.Str::lower(Str::random(20));
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    /** @return HasMany<MultiTradeBundleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MultiTradeBundleItem::class, 'bundle_id');
    }
}
