<?php

namespace App\Models;

use App\Services\PeerRental\Contracts\Louable;
use Database\Factories\PeerStayFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * UN LOGEMENT MIS EN LOCATION PAR UN MEMBRE.
 *
 * Il vit à côté du véhicule, pas à sa place : ils n'ont en commun que le CONTRAT de location, et
 * c'est ce contrat — `Louable` — que la couche d'argent connaît. Elle ignore tout des chambres,
 * comme elle ignore tout des boîtes de vitesses.
 *
 * @property ?string $published_at
 */
class PeerStay extends Model implements Louable
{
    /** @use HasFactory<PeerStayFactory> */
    use HasFactory;

    use SoftDeletes;

    public const STATUT_BROUILLON = 'draft';

    public const STATUT_EN_REVUE = 'pending_review';

    public const STATUT_PUBLIE = 'published';

    public const STATUT_REFUSE = 'rejected';

    public const STATUT_SUSPENDU = 'suspended';

    /** Ce que le voyageur occupe réellement : tout le logement, une chambre, ou un lit partagé. */
    public const ESPACES = ['entire', 'private_room', 'shared_room'];

    public const TYPES = ['appartement', 'maison', 'studio', 'chambre', 'loft', 'autre'];

    protected $fillable = [
        'reference', 'owner_id', 'organization_account_id',
        'status', 'published_at', 'rejection_reason', 'reviewed_by', 'reviewed_at',
        'title', 'description', 'property_type', 'space_type',
        'max_guests', 'bedrooms', 'beds', 'bathrooms', 'surface_m2', 'amenities', 'house_rules',
        'nightly_price_cents', 'currency', 'cleaning_fee_cents',
        'guests_included', 'extra_guest_price_cents',
        'discount_3_days_percent', 'discount_7_days_percent', 'discount_28_days_percent',
        'deposit_cents', 'min_nights', 'max_nights', 'check_in_from', 'check_out_before',
        'instant_booking', 'cancellation_policy',
        'address_line', 'postal_code', 'city', 'country_code', 'lat', 'lng',
        'metadata',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'amenities' => 'array',
        'metadata' => 'array',
        'instant_booking' => 'boolean',
        'bathrooms' => 'decimal:1',
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
    ];

    protected static function booted(): void
    {
        // LA REFERENCE EST L'IDENTITE PUBLIQUE d'une annonce : elle survit a un changement de
        // titre, et c'est elle qu'un membre cite quand il ecrit au support.
        static::creating(function (PeerStay $logement) {
            $logement->reference ??= 'STAY-'.Str::upper(Str::random(8));
        });
    }

    // ── Le contrat partagé ─────────────────────────────────────────────────

    public function typeDeBien(): string
    {
        return 'stay';
    }

    public function proprietaire(): ?User
    {
        return $this->owner;
    }

    public function estPubliable(): bool
    {
        return $this->status === self::STATUT_PUBLIE;
    }

    /** POUR UN LOGEMENT, LA NUIT EST LA JOURNEE : le contrat parle de jours, le prix de nuits. */
    public function prixJournalierCents(): int
    {
        return (int) $this->nightly_price_cents;
    }

    public function devise(): string
    {
        return (string) ($this->currency ?: 'EUR');
    }

    public function remisePourDuree(int $jours): int
    {
        return match (true) {
            $jours >= 28 => (int) $this->discount_28_days_percent,
            $jours >= 7 => (int) $this->discount_7_days_percent,
            $jours >= 3 => (int) $this->discount_3_days_percent,
            default => 0,
        };
    }

    public function cautionCents(): int
    {
        return (int) $this->deposit_cents;
    }

    public function dureeMinimum(): int
    {
        return max(1, (int) $this->min_nights);
    }

    public function dureeMaximum(): int
    {
        return max($this->dureeMinimum(), (int) $this->max_nights);
    }

    public function reservationInstantanee(): bool
    {
        return (bool) $this->instant_booking;
    }

    public function politiqueDAnnulation(): string
    {
        return (string) ($this->cancellation_policy ?: 'flexible');
    }

    /** @return MorphMany<PeerVehicleAvailability, $this> */
    public function indisponibilites(): MorphMany
    {
        return $this->morphMany(PeerVehicleAvailability::class, 'rentable');
    }

    // ── Relations propres au logement ──────────────────────────────────────

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class);
    }

    /** @return HasMany<PeerStayMedium, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(PeerStayMedium::class)->orderBy('position');
    }

    /** @return MorphMany<PeerRental, $this> */
    public function rentals(): MorphMany
    {
        return $this->morphMany(PeerRental::class, 'rentable');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePubliees(Builder $query): Builder
    {
        return $query->where('status', self::STATUT_PUBLIE);
    }

    /** Le supplément dû pour les voyageurs au-delà de ce que le prix couvre déjà. */
    public function supplementVoyageursCents(int $voyageurs): int
    {
        $enPlus = max(0, $voyageurs - max(1, (int) $this->guests_included));

        return $enPlus * (int) $this->extra_guest_price_cents;
    }

    /** @return list<string> */
    public function equipements(): array
    {
        return array_values(array_filter((array) ($this->amenities ?? [])));
    }

    public function photoPrincipale(): ?PeerStayMedium
    {
        return $this->media->first();
    }
}
