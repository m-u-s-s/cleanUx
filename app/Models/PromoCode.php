<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class PromoCode extends Model
{
    use HasFactory;

    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed_amount';
    public const TYPE_FREE_FIRST = 'free_first_booking';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_ARCHIVED = 'archived';

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_CAMPAIGN = 'campaign';
    public const SOURCE_REFERRAL = 'referral';
    public const SOURCE_SYSTEM = 'system';

    public const SCOPE_ALL = 'all';
    public const SCOPE_NEW = 'new_customers';
    public const SCOPE_RETURNING = 'returning_customers';
    public const SCOPE_B2B = 'b2b';
    public const SCOPE_SPECIFIC = 'specific_users';

    protected $fillable = [
        'promo_campaign_id',
        'code',
        'name',
        'description',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_booking_amount',
        'max_total_uses',
        'max_uses_per_user',
        'total_uses',
        'valid_from',
        'valid_until',
        'first_booking_only',
        'stackable_with_credits',
        'stackable_with_referral',
        'audience_scope',
        'allowed_trade_ids',
        'allowed_service_catalog_ids',
        'allowed_country_ids',
        'allowed_zone_ids',
        'allowed_user_ids',
        'status',
        'source',
        'issued_to_user_id',
        'created_by_user_id',
        'metadata',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'max_discount_amount' => 'decimal:2',
        'min_booking_amount' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'first_booking_only' => 'boolean',
        'stackable_with_credits' => 'boolean',
        'stackable_with_referral' => 'boolean',
        'allowed_trade_ids' => 'array',
        'allowed_service_catalog_ids' => 'array',
        'allowed_country_ids' => 'array',
        'allowed_zone_ids' => 'array',
        'allowed_user_ids' => 'array',
        'metadata' => 'array',
        'max_total_uses' => 'integer',
        'max_uses_per_user' => 'integer',
        'total_uses' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (PromoCode $promo) {
            if ($promo->code) {
                $promo->code = strtoupper(trim($promo->code));
            }
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PromoCampaign::class, 'promo_campaign_id');
    }

    public function issuedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_to_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }

    public function appliedRedemptions(): HasMany
    {
        return $this->redemptions()->where('status', PromoCodeRedemption::STATUS_APPLIED);
    }

    public function isWithinValidityWindow(?Carbon $at = null): bool
    {
        $at ??= now();

        if ($this->valid_from && $this->valid_from->greaterThan($at)) {
            return false;
        }
        if ($this->valid_until && $this->valid_until->lessThan($at)) {
            return false;
        }

        return true;
    }

    public function hasGlobalUsesLeft(): bool
    {
        if ($this->max_total_uses === null) {
            return true;
        }

        return (int) $this->total_uses < (int) $this->max_total_uses;
    }

    public function usesByUser(int $userId): int
    {
        return (int) $this->redemptions()
            ->where('user_id', $userId)
            ->whereIn('status', [PromoCodeRedemption::STATUS_APPLIED, PromoCodeRedemption::STATUS_RESERVED])
            ->count();
    }

    public function hasUserUsesLeft(int $userId): bool
    {
        if ($this->max_uses_per_user === null || (int) $this->max_uses_per_user === 0) {
            return true;
        }

        return $this->usesByUser($userId) < (int) $this->max_uses_per_user;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeUsableNow(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            });
    }

    public function scopeForCode(Builder $query, string $code): Builder
    {
        return $query->where('code', strtoupper(trim($code)));
    }
}
