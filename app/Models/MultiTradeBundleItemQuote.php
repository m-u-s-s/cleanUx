<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un devis soumis (ou sollicité) par un prestataire pour un item de chantier groupé.
 * Plusieurs devis concurrents peuvent exister par item — le client choisit.
 */
class MultiTradeBundleItemQuote extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_SELECTED = 'selected';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_WITHDRAWN = 'withdrawn';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'bundle_item_id', 'provider_user_id', 'status',
        'price_cents', 'message', 'valid_until', 'submitted_at', 'metadata',
    ];

    protected $casts = [
        'price_cents' => 'integer',
        'valid_until' => 'datetime',
        'submitted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(MultiTradeBundleItem::class, 'bundle_item_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    /** Devis encore ouverts (pending/submitted) et non expirés. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_PENDING, self::STATUS_SUBMITTED])
            ->where(function (Builder $q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>', now());
            });
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && $this->valid_until->isPast();
    }
}
