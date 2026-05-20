<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BusinessEntity extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const RISK_LOW = 'low';
    public const RISK_MEDIUM = 'medium';
    public const RISK_HIGH = 'high';
    public const RISK_CRITICAL = 'critical';

    protected $fillable = [
        'code', 'legal_name', 'trade_name',
        'country_code', 'identifier_type', 'identifier_value',
        'vat_id', 'legal_form', 'registered_address',
        'incorporation_date', 'status',
        'risk_score', 'risk_level',
        'owner_user_id', 'contact_user_id', 'contact_email',
        'verified_at', 'verified_by_user_id',
        'rejected_at', 'rejection_reason',
        'metadata',
    ];

    protected $casts = [
        'registered_address' => 'array',
        'incorporation_date' => 'date',
        'risk_score' => 'float',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
        'metadata' => 'array',
    ];

    public static function generateCode(): string
    {
        return 'biz_' . Str::lower(Str::random(20));
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BusinessDocument::class, 'entity_id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(BusinessVerification::class, 'entity_id');
    }

    public function sanctionsChecks(): HasMany
    {
        return $this->hasMany(BusinessSanctionsCheck::class, 'entity_id');
    }

    public function beneficialOwners(): HasMany
    {
        return $this->hasMany(BusinessBeneficialOwner::class, 'entity_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function scopeVerified(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_VERIFIED);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_PENDING, self::STATUS_NEEDS_REVIEW]);
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isCriticalRisk(): bool
    {
        return $this->risk_level === self::RISK_CRITICAL;
    }
}
