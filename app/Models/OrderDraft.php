<?php

namespace App\Models;

use App\Support\Domain\OrderDraftStatus;
use App\Support\Domain\OrderMode;
use App\Support\HumanReference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * La commande en cours de construction.
 *
 * `client_id` est nullable, et c'est le cœur du dispositif : le client voit un prix AVANT qu'on
 * lui demande un compte. Le jeton de session est ce qui lui permet de retrouver son panier trois
 * heures plus tard, dans un autre onglet, sans s'être jamais inscrit.
 *
 * Le brouillon se matérialise à la confirmation — une réservation pour un métier seul, un lot
 * multi-métiers pour plusieurs — et n'est jamais purgé : il porte les réponses horodatées qui
 * rendent le devis explicable ligne par ligne, et opposable.
 */
class OrderDraft extends Model
{
    protected $fillable = [
        'reference', 'client_id', 'session_token', 'mode', 'status',
        // Rattrapage quand le cookie de session a disparu : hachée, tournante, expirante.
        'recovery_key_hash', 'recovery_key_expires_at',
        'address', 'address_details', 'lat', 'lng',
        'scheduled_at', 'asap_requested_at',
        'estimate_min_cents', 'estimate_max_cents', 'total_cents', 'currency',
        'client_notes', 'source',
        'converted_booking_id', 'converted_bundle_id', 'converted_at', 'metadata',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'scheduled_at' => 'datetime',
        'asap_requested_at' => 'datetime',
        'converted_at' => 'datetime',
        'recovery_key_expires_at' => 'datetime',
        'estimate_min_cents' => 'integer',
        'estimate_max_cents' => 'integer',
        'total_cents' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Référence lisible, communicable au téléphone.
     *
     * Sans I, O, 0 ni 1 : la référence est destinée à être dictée, et ces quatre caractères sont
     * la première cause d'erreur de saisie au support.
     */
    public static function generateReference(): string
    {
        return HumanReference::prefixed('CLX-', 5);
    }

    /** @return HasMany<OrderDraftItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderDraftItem::class)->orderBy('sequence');
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', [OrderDraftStatus::DRAFT, OrderDraftStatus::SUBMITTED]);
    }

    public function isBundle(): bool
    {
        return $this->mode === OrderMode::BUNDLE;
    }

    public function isAsap(): bool
    {
        return $this->mode === OrderMode::ASAP;
    }

    /** Le panier appartient-il à ce visiteur ? Un compte prime toujours sur un jeton de session. */
    public function belongsToVisitor(?int $userId, ?string $sessionToken): bool
    {
        if ($this->client_id !== null) {
            return $userId !== null && (int) $this->client_id === $userId;
        }

        return $sessionToken !== null && hash_equals((string) $this->session_token, $sessionToken);
    }
}
