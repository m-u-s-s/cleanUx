<?php

namespace App\Models;

use Database\Factories\RentalBookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** UNE LOCATION DE VÉHICULE. CE N'EST PAS UN {@see Booking}, et c'est délibéré. */
class RentalBooking extends Model
{
    /** @use HasFactory<RentalBookingFactory> */
    use HasFactory;

    /** Le panier, avant que le client n'ait confirmé. Ne bloque aucune disponibilité. */
    public const STATUT_BROUILLON = 'draft';

    public const STATUT_CONFIRMEE = 'confirmed';

    public const STATUT_RETIREE = 'picked_up';

    public const STATUT_RENDUE = 'returned';

    public const STATUT_ANNULEE = 'cancelled';

    /**
     * LES STATUTS QUI RENDENT LE VÉHICULE INDISPONIBLE.
     *
     * @var list<string>
     */
    public const STATUTS_QUI_BLOQUENT = [self::STATUT_CONFIRMEE, self::STATUT_RETIREE];

    protected $fillable = [
        'reference', 'rental_vehicle_id', 'client_id', 'session_token',
        'starts_at', 'ends_at', 'days',
        'driver_first_name', 'driver_last_name', 'driver_birthdate',
        'driver_email', 'driver_phone',
        'license_number', 'license_country', 'license_issued_at',
        'protection',
        'daily_price_cents', 'subtotal_cents', 'waiver_total_cents',
        'total_cents', 'deposit_cents', 'currency',
        'pickup_label', 'pickup_address', 'pickup_lat', 'pickup_lng',
        'status', 'stripe_payment_intent_id', 'imprint_authorized_at',
        'confirmed_at', 'picked_up_at', 'returned_at', 'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'driver_birthdate' => 'date',
        'license_issued_at' => 'date',
        'imprint_authorized_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'returned_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
        'days' => 'integer',
        'daily_price_cents' => 'integer',
        'subtotal_cents' => 'integer',
        'waiver_total_cents' => 'integer',
        'total_cents' => 'integer',
        'deposit_cents' => 'integer',
        'pickup_lat' => 'float',
        'pickup_lng' => 'float',
    ];

    public static function genererUneReference(): string
    {
        return 'LOC'.now()->format('ymd').strtoupper(Str::random(5));
    }

    /** @return BelongsTo<RentalVehicle, $this> */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(RentalVehicle::class, 'rental_vehicle_id');
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @param  Builder<RentalBooking>  $query */
    public function scopeQuiBloque(Builder $query): void
    {
        $query->whereIn('status', self::STATUTS_QUI_BLOQUENT);
    }

    public function estAvecGarantie(): bool
    {
        return $this->protection === RentalVehicle::PROTECTION_AVEC;
    }

    public function estAnnulable(): bool
    {
        return in_array($this->status, [self::STATUT_BROUILLON, self::STATUT_CONFIRMEE], true);
    }

    /** Le nom du conducteur, pour les écrans d'administration. */
    public function nomDuConducteur(): string
    {
        $nom = trim(($this->driver_first_name ?? '').' '.($this->driver_last_name ?? ''));

        return $nom !== '' ? $nom : ($this->driver_email ?? '—');
    }
}
