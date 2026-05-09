<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 13 — Versement à un prestataire.
 *
 * La table existe déjà (migration 2026_05_04_000009_create_finance_tables.php).
 * Ce modèle expose les colonnes existantes + helpers/scopes.
 *
 * Cycle de vie typique :
 *   1. Mission complétée → captures du PaymentIntent (Stripe transfer auto vers provider Connect account)
 *   2. ProviderPayout créé en status 'pending' (entrée comptable côté plateforme)
 *   3. Stripe regroupe les transfers vers le compte Connect en payouts (via leur logique standard)
 *   4. Webhook Stripe payout.paid / payout.failed → on met à jour status + provider_payout_id
 */
class ProviderPayout extends Model
{
    use HasFactory;

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID       = 'paid';
    public const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'provider_user_id',
        'provider_organization_id',
        'amount',
        'currency',
        'status',
        'provider',
        'provider_payout_id',
        'period_start',
        'period_end',
        'metadata',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'period_start' => 'date',
        'period_end'   => 'date',
        'metadata'     => 'array',
    ];

    // ──────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────

    public function providerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_user_id');
    }

    public function providerOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'provider_organization_id');
    }

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopePending(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PENDING);
    }

    public function scopePaid(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_PAID);
    }

    public function scopeForProvider(Builder $q, int $userId): Builder
    {
        return $q->where('provider_user_id', $userId);
    }

    public function scopeBetween(Builder $q, $from, $to): Builder
    {
        return $q
            ->whereDate('period_start', '>=', $from)
            ->whereDate('period_end', '<=', $to);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function markAsProcessing(?string $stripePayoutId = null): void
    {
        $this->update([
            'status'             => self::STATUS_PROCESSING,
            'provider_payout_id' => $stripePayoutId ?? $this->provider_payout_id,
        ]);
    }

    public function markAsPaid(?string $stripePayoutId = null): void
    {
        $this->update([
            'status'             => self::STATUS_PAID,
            'provider_payout_id' => $stripePayoutId ?? $this->provider_payout_id,
        ]);
    }

    public function markAsFailed(?array $metadata = null): void
    {
        $this->update([
            'status'   => self::STATUS_FAILED,
            'metadata' => array_merge((array) $this->metadata, $metadata ?? []),
        ]);
    }
}
