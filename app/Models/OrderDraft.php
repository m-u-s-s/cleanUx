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
 * @property ?string $beneficiary_name
 * @property ?string $beneficiary_phone
 * @property ?string $beneficiary_note
 * @property ?int $client_place_id
 * @property ?string $dropoff_address
 * @property ?float $dropoff_lat
 * @property ?float $dropoff_lng
 * @property ?string $dropoff_postal_code
 * @property ?int $route_distance_m
 * @property ?int $route_duration_s
 * @property ?string $route_source
 */
class OrderDraft extends Model
{
    protected $fillable = [
        'reference', 'client_id', 'session_token', 'mode', 'status',
        // Rattrapage quand le cookie de session a disparu : hachée, tournante, expirante.
        'recovery_key_hash', 'recovery_key_expires_at',
        'address', 'address_details', 'lat', 'lng',
        // La géographie résolue PENDANT le parcours : c'est elle qui donne au prix sa grille de
        // zone, et au dispatch un point de départ au lieu d'une adresse à redeviner.
        'postal_code', 'service_zone_id',
        // LE POINT D'ARRIVÉE, sur les seuls métiers de trajet.
        'dropoff_address', 'dropoff_lat', 'dropoff_lng', 'dropoff_postal_code',
        // Mesurés à la commande, pour annoncer un prix au kilomètre AVANT que le client valide.
        'route_distance_m', 'route_duration_s', 'route_source',
        'scheduled_at', 'asap_requested_at',
        'estimate_min_cents', 'estimate_max_cents', 'total_cents', 'currency',
        'client_notes', 'source',
        'converted_booking_id', 'converted_bundle_id', 'converted_at', 'metadata',
        // LE BÉNÉFICIAIRE (E1) — le client paye, quelqu'un d'autre reçoit.
        'beneficiary_name', 'beneficiary_phone', 'beneficiary_note',
        // Le lieu du carnet retenu pour cette commande (E2).
        'client_place_id',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'dropoff_lat' => 'float',
        'dropoff_lng' => 'float',
        'route_distance_m' => 'integer',
        'route_duration_s' => 'integer',
        'scheduled_at' => 'datetime',
        'asap_requested_at' => 'datetime',
        'converted_at' => 'datetime',
        'recovery_key_expires_at' => 'datetime',
        'estimate_min_cents' => 'integer',
        'estimate_max_cents' => 'integer',
        'total_cents' => 'integer',
        'metadata' => 'array',
    ];

    /** Référence lisible, communicable au téléphone. */
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
