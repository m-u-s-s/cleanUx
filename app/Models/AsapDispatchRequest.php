<?php

namespace App\Models;

use App\Support\Domain\AsapStatus;
use Database\Factories\AsapDispatchRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une recherche de prestataire immédiat.
 *
 * Porte la phase où le client attend devant son écran — avant qu'une mission existe, donc avant
 * que le dispatch habituel puisse s'en charger.
 */
class AsapDispatchRequest extends Model
{
    /** @use HasFactory<AsapDispatchRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'order_draft_id', 'order_draft_item_id', 'trade_id', 'status',
        'lat', 'lng', 'radius_m', 'notified_count', 'expansion_count',
        'accepted_by_user_id',
        'searching_at', 'accepted_at', 'en_route_at', 'arrived_at',
        'in_progress_at', 'completed_at', 'cancelled_at',
        'free_cancellation_until', 'cancelled_by', 'cancellation_reason',
        'cancellation_fee_cents', 'metadata',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'radius_m' => 'integer',
        'notified_count' => 'integer',
        'expansion_count' => 'integer',
        'cancellation_fee_cents' => 'integer',
        'searching_at' => 'datetime',
        'accepted_at' => 'datetime',
        'en_route_at' => 'datetime',
        'arrived_at' => 'datetime',
        'in_progress_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'free_cancellation_until' => 'datetime',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<OrderDraft, $this> */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(OrderDraft::class, 'order_draft_id');
    }

    /** @return BelongsTo<OrderDraftItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(OrderDraftItem::class, 'order_draft_item_id');
    }

    /** @return BelongsTo<Trade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    /** @return HasMany<AsapDispatchNotification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(AsapDispatchNotification::class, 'asap_dispatch_request_id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereNotIn('status', [AsapStatus::COMPLETED, AsapStatus::CANCELLED, AsapStatus::EXPIRED]);
    }

    public function isOpen(): bool
    {
        return AsapStatus::isOpen($this->status);
    }

    /** Depuis combien de temps le client attend. C'est ce que l'écran affiche. */
    public function elapsedSeconds(): int
    {
        return (int) ($this->searching_at?->diffInSeconds(now()) ?? 0);
    }

    /** L'annulation est-elle encore gratuite à cet instant ? */
    public function cancellationIsFree(): bool
    {
        if ($this->status === AsapStatus::SEARCHING) {
            // Tant que personne n'a pris la course, personne ne s'est déplacé : rien à facturer.
            return true;
        }

        return $this->free_cancellation_until !== null && now()->lte($this->free_cancellation_until);
    }
}
