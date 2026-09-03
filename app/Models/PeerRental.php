<?php

namespace App\Models;

use App\Services\PeerRental\Contracts\Louable;
use Database\Factories\PeerRentalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

/**
 * UNE LOCATION ENTRE DEUX MEMBRES.
 *
 * Ce n'est ni un {@see Booking} (une prestation) ni un {@see RentalBooking} (la flotte de la
 * plateforme) : deux comptes, une commission, et un paiement bloque a la reservation puis
 * capture a la remise des cles, quand LES DEUX ont confirme.
 */
class PeerRental extends Model
{
    /** @use HasFactory<PeerRentalFactory> */
    use HasFactory;

    /** Le proprietaire n'a pas encore repondu. Les fonds sont deja bloques. */
    public const STATUT_EN_ATTENTE = 'pending_owner';

    public const STATUT_CONFIRMEE = 'confirmed';

    public const STATUT_REFUSEE = 'declined';

    public const STATUT_EN_COURS = 'handed_over';

    public const STATUT_RENDUE = 'returned';

    public const STATUT_TERMINEE = 'completed';

    public const STATUT_ANNULEE = 'cancelled';

    /** Le proprietaire n'a pas repondu a temps : les fonds sont rendus. */
    public const STATUT_EXPIREE = 'expired';

    public const STATUT_LITIGE = 'disputed';

    /**
     * LES STATUTS QUI RENDENT LE VEHICULE INDISPONIBLE.
     *
     * `pending_owner` en fait partie : sans cela, deux locataires pourraient bloquer les
     * memes dates en attendant la meme reponse.
     *
     * @var list<string>
     */
    public const STATUTS_QUI_BLOQUENT = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_CONFIRMEE,
        self::STATUT_EN_COURS,
    ];

    public const PAIEMENT_EN_ATTENTE = 'pending';

    public const PAIEMENT_AUTORISE = 'authorized';

    public const PAIEMENT_CAPTURE = 'captured';

    public const PAIEMENT_REMBOURSE = 'refunded';

    public const PAIEMENT_ECHOUE = 'failed';

    /** L'autorisation Stripe est tombee avant la remise, faute de re-autorisation. */
    public const PAIEMENT_EXPIRE = 'expired';

    protected $fillable = [
        'reference', 'peer_vehicle_id', 'rentable_type', 'rentable_id', 'owner_id', 'renter_id', 'status',
        'starts_at', 'ends_at', 'days',
        'delivery_requested', 'delivery_address', 'delivery_lat', 'delivery_lng',
        'daily_price_cents', 'subtotal_cents', 'discount_cents', 'delivery_cents',
        'insurance_cents', 'total_cents', 'currency',
        'platform_fee_cents', 'owner_payout_cents', 'commission_rate',
        'deposit_cents', 'included_km', 'extra_km_price_cents', 'extra_charges_cents',
        'payment_status', 'stripe_payment_intent_id', 'payment_authorized_at',
        'payment_authorized_until', 'payment_captured_at', 'reauthorized_count',
        'deposit_payment_intent_id', 'deposit_authorized_at', 'deposit_captured_cents', 'deposit_released_at',
        'handover_owner_confirmed_at', 'handover_renter_confirmed_at', 'handed_over_at',
        'return_owner_confirmed_at', 'return_renter_confirmed_at', 'returned_at',
        'accepted_at', 'declined_at', 'cancelled_at', 'cancelled_by',
        'cancellation_fee_cents', 'cancellation_reason',
        'insurance_plan_key', 'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'payment_authorized_at' => 'datetime',
        'payment_authorized_until' => 'datetime',
        'payment_captured_at' => 'datetime',
        'deposit_authorized_at' => 'datetime',
        'deposit_released_at' => 'datetime',
        'handover_owner_confirmed_at' => 'datetime',
        'handover_renter_confirmed_at' => 'datetime',
        'handed_over_at' => 'datetime',
        'return_owner_confirmed_at' => 'datetime',
        'return_renter_confirmed_at' => 'datetime',
        'returned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
        'delivery_requested' => 'boolean',
        'days' => 'integer',
        'daily_price_cents' => 'integer',
        'subtotal_cents' => 'integer',
        'discount_cents' => 'integer',
        'delivery_cents' => 'integer',
        'insurance_cents' => 'integer',
        'total_cents' => 'integer',
        'platform_fee_cents' => 'integer',
        'owner_payout_cents' => 'integer',
        'commission_rate' => 'float',
        'deposit_cents' => 'integer',
        'included_km' => 'integer',
        'extra_km_price_cents' => 'integer',
        'extra_charges_cents' => 'integer',
        'deposit_captured_cents' => 'integer',
        'reauthorized_count' => 'integer',
        'delivery_lat' => 'float',
        'delivery_lng' => 'float',
    ];

    public static function genererUneReference(): string
    {
        return 'PL'.now()->format('ymd').strtoupper(Str::random(6));
    }

    /**
     * LES DEUX COLONNES DISENT LE MEME BIEN, ET DOIVENT LE DIRE ENSEMBLE.
     *
     * Tout le module vehicules ecrit `peer_vehicle_id` ; la couche partagee lit `rentable_*`.
     * Sans ce crochet, une location creee par l'ancienne voie serait INVISIBLE au controle de
     * chevauchement — et deux locataires retiendraient les memes dates sans que rien ne l'empeche.
     * C'est un test qui l'a montre, pas une relecture.
     */
    protected static function booted(): void
    {
        static::saving(function (self $location) {
            if ($location->rentable_type === null && $location->peer_vehicle_id !== null) {
                $location->rentable_type = PeerVehicle::class;
                $location->rentable_id = $location->peer_vehicle_id;
            }
        });
    }

    /**
     * LE BIEN LOUE, QUEL QU'IL SOIT.
     *
     * `vehicle()` reste pour tout le module vivant ; cette relation-ci est celle que la couche
     * partagee interroge, et la seule qui sache repondre pour un logement.
     *
     * @return MorphTo<Model, $this>
     */
    public function rentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<PeerVehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(PeerVehicle::class, 'peer_vehicle_id');
    }

    /**
     * LE BIEN, POUR CEUX QUI DOIVENT L'AFFICHER.
     *
     * Les ecrans partages — la liste, le detail, l'arbitrage — rendaient `vehicle`, donc `null`
     * des qu'un logement passait par la : le titre plantait la page entiere. Ils passent par ici.
     *
     * Le repli sur `vehicle` sert les lignes anterieures a la colonne polymorphe.
     */
    public function bien(): (Louable&Model)|null
    {
        $bien = $this->rentable ?? $this->vehicle;

        return $bien instanceof Louable ? $bien : null;
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function renter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'renter_id');
    }

    /** @return HasMany<PeerInspection, $this> */
    public function inspections(): HasMany
    {
        return $this->hasMany(PeerInspection::class);
    }

    /** @return HasMany<PeerCode, $this> */
    public function codes(): HasMany
    {
        return $this->hasMany(PeerCode::class);
    }

    /** @return HasMany<PeerClaim, $this> */
    public function claims(): HasMany
    {
        return $this->hasMany(PeerClaim::class);
    }

    /** @return HasMany<PeerReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(PeerReview::class);
    }

    /**
     * @param  Builder<PeerRental>  $query
     * @return Builder<PeerRental>
     */
    public function scopeQuiBloquent(Builder $query): Builder
    {
        return $query->whereIn('status', self::STATUTS_QUI_BLOQUENT);
    }

    /** LES DEUX SIGNATURES, PAS UNE. C'est cette condition qui declenche la capture. */
    public function remiseConfirmeeParLesDeux(): bool
    {
        return $this->handover_owner_confirmed_at !== null
            && $this->handover_renter_confirmed_at !== null;
    }

    public function retourConfirmeParLesDeux(): bool
    {
        return $this->return_owner_confirmed_at !== null
            && $this->return_renter_confirmed_at !== null;
    }

    public function estActive(): bool
    {
        return in_array($this->status, self::STATUTS_QUI_BLOQUENT, true);
    }

    public function inspection(string $phase): ?PeerInspection
    {
        return $this->inspections->firstWhere('phase', $phase);
    }
}
