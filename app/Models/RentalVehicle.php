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

/**
 * UNE VOITURE QUE LA PLATEFORME LOUE À SES CLIENTS.
 *
 * À NE PAS CONFONDRE AVEC {@see FleetVehicle}, et la confusion serait coûteuse. Fleet est le
 * registre d'un EMPLOYEUR : ce qu'une société possède et confie à ses exécutants pour aller
 * travailler, sans transaction. Ici le véhicule est un PRODUIT — un prix par jour, une caution, une
 * garantie optionnelle, un permis à vérifier, une agence où venir le chercher.
 *
 * Les deux tables ne se parlent pas et ne partagent aucune ligne. Une même voiture physique
 * pourrait figurer dans les deux, et ce serait deux enregistrements distincts décrivant deux
 * usages distincts — pas une duplication.
 */
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
     * L'ORDRE EST LE SENS DE ROTATION : deux images interverties font sauter la voiture en arrière
     * au milieu du geste. C'est la seule relation du modèle où `position` porte du sens et non un
     * simple confort d'affichage.
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
     * On exclut par ABSENCE DE CHEVAUCHEMENT, et le sens de la comparaison est le piège classique :
     * deux périodes se chevauchent dès que l'une commence avant que l'autre ne finisse ET finit
     * après que l'autre a commencé. Écrire l'inverse laisse passer les locations enchâssées — celles
     * qui tiennent entièrement à l'intérieur d'une autre, c'est-à-dire le cas le plus courant d'une
     * location courte pendant une longue.
     *
     * Seules les locations VIVANTES bloquent : une annulée ou une rendue ne réserve plus rien.
     *
     * @param  Builder<RentalVehicle>  $query
     */
    public function scopeLibreEntre(Builder $query, ?CarbonInterface $debut, ?CarbonInterface $fin): void
    {
        if ($debut === null || $fin === null) {
            /*
             * SANS DATES, ON N'ÉCARTE QUE CE QUI EST DEHORS MAINTENANT.
             *
             * Le catalogue s'ouvre avant que le client n'ait choisi ses dates. Tout masquer
             * faute de période donnerait une vitrine vide ; ne rien masquer montrerait une voiture
             * physiquement absente du parking. On montre donc ce qui est là aujourd'hui.
             */
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

    /**
     * La caution demandée selon que le client prend la garantie ou non.
     *
     * C'est l'argument commercial de la garantie et il doit être visible : sans elle la caution est
     * pleine, avec elle elle tombe. Un client qui ne voit que le supplément journalier ne comprend
     * pas ce qu'il achète.
     */
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
