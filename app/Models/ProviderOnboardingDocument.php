<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 14 — Document KYC uploadé par un prestataire.
 *
 * Cycle de vie :
 *   pending_review  →  approved   (admin valide)
 *                  →  rejected   (avec raison visible)
 */
class ProviderOnboardingDocument extends Model
{
    use HasFactory;

    public const STATUS_PENDING  = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const TYPE_IDENTITY_CARD     = 'identity_card';
    public const TYPE_PASSPORT          = 'passport';
    public const TYPE_RESIDENCE_PERMIT  = 'residence_permit';
    public const TYPE_TAX_ID            = 'tax_id';
    public const TYPE_INSURANCE         = 'insurance';
    public const TYPE_DIPLOMA           = 'diploma';
    public const TYPE_CRIMINAL_RECORD   = 'criminal_record';
    public const TYPE_OTHER             = 'other';

    /**
     * Documents requis pour la complétion onboarding (peut être surchargé en config).
     */
    public const REQUIRED_TYPES = [
        self::TYPE_IDENTITY_CARD, // l'un OU l'autre des 3 (gestion dans le service)
        self::TYPE_INSURANCE,
    ];

    protected $fillable = [
        'user_id',
        'document_type',
        'status',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'file_size'   => 'integer',
        'reviewed_at' => 'datetime',
        'expires_at'  => 'date',
        'metadata'    => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeApproved(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_APPROVED);
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
