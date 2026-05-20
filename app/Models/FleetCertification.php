<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FleetCertification extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRING_SOON = 'expiring_soon';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    public const SUBJECT_VEHICLE = 'vehicle';
    public const SUBJECT_EQUIPMENT = 'equipment';
    public const SUBJECT_PROVIDER = 'provider';

    protected $fillable = [
        'subject_type', 'subject_id',
        'certification_type', 'reference',
        'issued_at', 'expires_at',
        'issuing_authority', 'document_path',
        'status', 'created_by_user_id', 'metadata',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'expires_at' => 'date',
        'metadata' => 'array',
    ];

    public function scopeForSubject(Builder $q, string $type, int $id): Builder
    {
        return $q->where('subject_type', $type)->where('subject_id', $id);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', [self::STATUS_ACTIVE, self::STATUS_EXPIRING_SOON]);
    }

    public function scopeExpiringWithin(Builder $q, int $days): Builder
    {
        return $q->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if (! $this->expires_at) {
            return false;
        }
        return $this->expires_at->isAfter(now()) && $this->expires_at->isBefore(now()->addDays($days));
    }
}
