<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProviderTradeCertification — proof of qualification for a provider on a given trade.
 *
 * certification_type values: diploma | attestation | experience
 * status values:             pending | verified | rejected
 *
 * The unique constraint (user_id, trade_id, certification_type) ensures at most one
 * record per qualification kind per trade per provider.
 */
class ProviderTradeCertification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'trade_id',
        'certification_type',
        'document_path',
        'status',
        'verified_by_user_id',
        'verified_at',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'verified_at' => 'datetime',
        'expires_at'  => 'datetime',
    ];

    // ──────────────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    // ──────────────────────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────────────────────

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', 'pending');
    }

    public function scopeVerified(Builder $q): Builder
    {
        return $q->where('status', 'verified');
    }

    public function scopeExpired(Builder $q): Builder
    {
        return $q->where('expires_at', '<', now());
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
