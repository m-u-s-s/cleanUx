<?php

namespace App\Models;

use Database\Factories\PeerVehicleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * UN VEHICULE QU'UN MEMBRE LOUE A D'AUTRES MEMBRES.
 *
 * Ce n'est PAS un {@see RentalVehicle} : celui-la appartient a la plateforme et se loue depuis
 * « Nos locations ». Ici le proprietaire est un compte, la plateforme prend une commission, et
 * l'argent transite par Stripe Connect.
 */
class PeerVehicle extends Model
{
    /** @use HasFactory<PeerVehicleFactory> */
    use HasFactory;

    use SoftDeletes;

    /** Le proprietaire redige encore : rien n'est visible, rien n'est reservable. */
    public const STATUT_BROUILLON = 'draft';

    public const STATUT_EN_REVUE = 'pending_review';

    public const STATUT_PUBLIE = 'published';

    /** Le proprietaire suspend son annonce sans la supprimer : les locations en cours vivent. */
    public const STATUT_EN_PAUSE = 'paused';

    public const STATUT_REFUSE = 'rejected';

    public const STATUT_ARCHIVE = 'archived';

    public const TRANSMISSION_MANUELLE = 'manuelle';

    public const TRANSMISSION_AUTOMATIQUE = 'automatique';

    /** Souple : remboursement integral jusqu'a 24 h. Stricte : rien au-dela de la reservation. */
    public const ANNULATION_SOUPLE = 'souple';

    public const ANNULATION_MODEREE = 'moderee';

    public const ANNULATION_STRICTE = 'stricte';

    protected $fillable = [
        'reference', 'owner_id', 'organization_account_id',
        'status', 'published_at', 'rejection_reason', 'reviewed_by', 'reviewed_at',
        'brand', 'model', 'year', 'color', 'plate', 'vin',
        'category', 'transmission', 'fuel', 'seats', 'doors', 'luggage', 'features', 'description',
        'daily_price_cents', 'currency', 'pricing_rules',
        'discount_3_days_percent', 'discount_7_days_percent', 'discount_28_days_percent',
        'deposit_cents', 'included_km_per_day', 'extra_km_price_cents',
        'min_rental_days', 'max_rental_days', 'min_driver_age', 'min_license_years', 'instant_booking',
        'address_line', 'postal_code', 'city', 'country_code', 'lat', 'lng',
        'delivery_enabled', 'delivery_radius_km', 'delivery_price_cents',
        'telematics_provider', 'telematics_device_id',
        'cancellation_policy', 'metadata',
    ];

    protected $casts = [
        'features' => 'array',
        'pricing_rules' => 'array',
        'metadata' => 'array',
        'published_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'instant_booking' => 'boolean',
        'delivery_enabled' => 'boolean',
        'year' => 'integer',
        'seats' => 'integer',
        'doors' => 'integer',
        'luggage' => 'integer',
        'daily_price_cents' => 'integer',
        'discount_3_days_percent' => 'integer',
        'discount_7_days_percent' => 'integer',
        'discount_28_days_percent' => 'integer',
        'deposit_cents' => 'integer',
        'included_km_per_day' => 'integer',
        'extra_km_price_cents' => 'integer',
        'min_rental_days' => 'integer',
        'max_rental_days' => 'integer',
        'min_driver_age' => 'integer',
        'min_license_years' => 'integer',
        'delivery_radius_km' => 'integer',
        'delivery_price_cents' => 'integer',
        'lat' => 'float',
        'lng' => 'float',
    ];

    public static function genererUneReference(): string
    {
        return 'PV'.now()->format('ymd').strtoupper(Str::random(6));
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return HasMany<PeerVehicleMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(PeerVehicleMedia::class)->orderBy('sort_order');
    }

    /** @return HasMany<PeerVehicleAvailability, $this> */
    public function availability(): HasMany
    {
        return $this->hasMany(PeerVehicleAvailability::class);
    }

    /** @return HasMany<PeerVehicleDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(PeerVehicleDocument::class);
    }

    /** @return HasMany<PeerRental, $this> */
    public function rentals(): HasMany
    {
        return $this->hasMany(PeerRental::class);
    }

    /**
     * LES ANNONCES QU'UN LOCATAIRE PEUT VOIR.
     *
     * @param  Builder<PeerVehicle>  $query
     * @return Builder<PeerVehicle>
     */
    public function scopePubliees(Builder $query): Builder
    {
        return $query->where('status', self::STATUT_PUBLIE);
    }

    public function estPubliee(): bool
    {
        return $this->status === self::STATUT_PUBLIE;
    }

    public function titre(): string
    {
        return trim($this->brand.' '.$this->model);
    }

    /**
     * LES EQUIPEMENTS, TOUJOURS SOUS FORME DE LISTE.
     *
     * `features` est castee en tableau, mais une ecriture doublement encodee — un import,
     * un script de peuplement — laisse une CHAINE apres decodage. La vue faisait alors
     * `foreach` sur une chaine et la page entiere rendait 500. Le contrat se tient ici,
     * une fois, plutot que dans chaque vue qui affichera un jour ce champ.
     *
     * @return list<string>
     */
    public function equipements(): array
    {
        $brut = $this->features;

        // PHPStan croit le cast, la base disait autre chose : c'est bien ce decalage que
        // l'on rattrape, et un `is_string()` ici serait juge impossible par le declare.
        if (! is_array($brut)) {
            $brut = json_decode((string) $brut, true);
        }

        if (! is_array($brut)) {
            return [];
        }

        return array_values(array_filter($brut, 'is_string'));
    }

    /** La photo de couverture, ou la premiere. Une annonce sans photo n'est pas publiable. */
    public function photoPrincipale(): ?PeerVehicleMedia
    {
        return $this->media->firstWhere('is_cover', true) ?? $this->media->first();
    }
}
