<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\RentalVehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** UNE VOITURE QUE LA PLATEFORME LOUE À SES CLIENTS. */
class RentalVehicle extends Model
{
    /** @use HasFactory<RentalVehicleFactory> */
    use HasFactory;

    use SoftDeletes;

    /** Ce que le client verra comme « boîte de vitesses ». */
    public const TRANSMISSION_MANUELLE = 'manuelle';

    public const TRANSMISSION_AUTOMATIQUE = 'automatique';

    /** Sans garantie : caution pleine. Avec : supplément par jour, caution réduite. */
    public const PROTECTION_SANS = 'none';

    public const PROTECTION_AVEC = 'waiver';

    protected $fillable = [
        'code', 'plate', 'brand', 'model', 'year', 'color',
        'category', 'transmission', 'fuel',
        'seats', 'doors', 'luggage', 'features',
        'daily_price_cents', 'currency',
        'deposit_cents', 'waiver_daily_price_cents', 'waiver_deposit_cents',
        'included_km_per_day', 'extra_km_price_cents',
        'min_rental_days', 'max_rental_days',
        'min_driver_age', 'min_license_years',
        'pickup_point_id', 'is_active', 'sort_order',
        'description', 'metadata',
    ];

    protected $casts = [
        'features' => 'array',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'year' => 'integer',
        'seats' => 'integer',
        'doors' => 'integer',
        'luggage' => 'integer',
        'daily_price_cents' => 'integer',
        'deposit_cents' => 'integer',
        'waiver_daily_price_cents' => 'integer',
        'waiver_deposit_cents' => 'integer',
        'included_km_per_day' => 'integer',
        'extra_km_price_cents' => 'integer',
        'min_rental_days' => 'integer',
        'max_rental_days' => 'integer',
        'min_driver_age' => 'integer',
        'min_license_years' => 'integer',
        'sort_order' => 'integer',
    ];

    public static function genererUnCode(): string
    {
        return 'LOC-'.strtoupper(Str::random(8));
    }

    /** @return BelongsTo<RentalPickupPoint, $this> */
    public function pickupPoint(): BelongsTo
    {
        return $this->belongsTo(RentalPickupPoint::class, 'pickup_point_id');
    }

    /** @return HasMany<RentalVehicleMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(RentalVehicleMedia::class);
    }

    /** @return HasMany<RentalBooking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(RentalBooking::class);
    }

    /**
     * Les photos de la galerie, dans l'ordre voulu par l'administrateur.
     *
     * @return HasMany<RentalVehicleMedia, $this>
     */
    public function galerie(): HasMany
    {
        return $this->media()
            ->where('type', RentalVehicleMedia::TYPE_GALERIE)
            ->orderBy('position');
    }

    /**
     * La séquence de rotation, dans l'ordre.
     *
     * @return HasMany<RentalVehicleMedia, $this>
     */
    public function rotation360(): HasMany
    {
        return $this->media()
            ->where('type', RentalVehicleMedia::TYPE_ROTATION)
            ->orderBy('position');
    }

    /** @return HasMany<RentalVehicleMedia, $this> */
    public function modele3d(): HasMany
    {
        return $this->media()->where('type', RentalVehicleMedia::TYPE_MODELE_3D);
    }

    // ── Ce que le catalogue demande ──────────────────────────────────────

    /** @param  Builder<RentalVehicle>  $query */
    public function scopeActif(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<RentalVehicle>  $query */
    public function scopeOrdonne(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('brand')->orderBy('model');
    }

    /**
     * LES VÉHICULES LIBRES SUR LA PÉRIODE — c'est la règle « ne pas afficher les voitures louées ».
     *
     * @param  Builder<RentalVehicle>  $query
     */
    public function scopeLibreEntre(Builder $query, ?CarbonInterface $debut, ?CarbonInterface $fin): void
    {
        if ($debut === null || $fin === null) {
            // SANS DATES, ON N'ÉCARTE QUE CE QUI EST DEHORS MAINTENANT.
            $debut = Carbon::now();
            $fin = Carbon::now();
        }

        $query->whereDoesntHave('bookings', function (Builder $q) use ($debut, $fin) {
            $q->whereIn('status', RentalBooking::STATUTS_QUI_BLOQUENT)
                ->where('starts_at', '<', $fin)
                ->where('ends_at', '>', $debut);
        });
    }

    // ── Les chiffres que le client compare ───────────────────────────────

    /** Le prix de la location seule, sans garantie. */
    public function totalSansGarantie(int $jours): int
    {
        return $this->daily_price_cents * max(1, $jours);
    }

    /** Le prix avec la garantie : la location, plus le supplément journalier. */
    public function totalAvecGarantie(int $jours): int
    {
        $jours = max(1, $jours);

        return ($this->daily_price_cents + $this->waiver_daily_price_cents) * $jours;
    }

    /** La caution demandée selon que le client prend la garantie ou non. */
    public function cautionPour(string $protection): int
    {
        return $protection === self::PROTECTION_AVEC
            ? $this->waiver_deposit_cents
            : $this->deposit_cents;
    }

    /** Ce véhicule propose-t-il seulement une garantie ? Certains n'en ont pas. */
    public function proposeUneGarantie(): bool
    {
        return $this->waiver_daily_price_cents > 0 || $this->waiver_deposit_cents < $this->deposit_cents;
    }

    /** L'image qui représente la voiture au catalogue. */
    public function vignette(): ?RentalVehicleMedia
    {
        return $this->galerie->first() ?? $this->rotation360->first();
    }

    public function nomComplet(): string
    {
        return trim($this->brand.' '.$this->model);
    }
}
