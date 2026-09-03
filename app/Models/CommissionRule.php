<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UNE RÈGLE DE COMMISSION — un taux, et le cas où il s'applique.
 *
 * Tous les discriminants sont facultatifs. Une règle qui n'en porte aucun est le taux général ;
 * une règle qui en porte quatre est la plus précise, et c'est elle qui gagne.
 */
class CommissionRule extends Model
{
    /** LES MODULES QUI PRÉLÈVENT — les seuls que le résolveur reconnaît. */
    public const MODULE_PRESTATION = 'prestation';

    public const MODULE_LOCATION_MEMBRES = 'peer_rental';

    public const MODULE_NOS_LOCATIONS = 'rental';

    public const MODULE_POURBOIRE = 'tips';

    public const MODULE_ABONNEMENT = 'subscription';

    /** @var array<string, string> */
    public const MODULES = [
        self::MODULE_PRESTATION => 'Prestations (missions)',
        self::MODULE_LOCATION_MEMBRES => 'Location entre membres',
        self::MODULE_NOS_LOCATIONS => 'Nos locations (flotte)',
        self::MODULE_POURBOIRE => 'Pourboires',
        self::MODULE_ABONNEMENT => 'Abonnements',
    ];

    protected $fillable = [
        'label', 'note', 'module', 'asset_type', 'trade_id', 'service_zone_id',
        'min_duration_days', 'starts_on', 'ends_on',
        'percent', 'min_cents', 'is_active', 'priority', 'updated_by',
    ];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
        'percent' => 'float',
        'min_cents' => 'integer',
        'is_active' => 'boolean',
        'priority' => 'integer',
        'min_duration_days' => 'integer',
    ];

    /** @return BelongsTo<Trade, $this> */
    public function trade(): BelongsTo
    {
        return $this->belongsTo(Trade::class);
    }

    /** @return BelongsTo<ServiceZone, $this> */
    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    /**
     * COMBIEN DE CONDITIONS CETTE RÈGLE POSE-T-ELLE ?
     *
     * C'est le rang de précision : quatre conditions battent trois, trois battent deux. Sans lui,
     * poser un taux de zone effacerait par accident un taux de métier.
     */
    public function precision(): int
    {
        return count(array_filter([
            $this->module,
            $this->asset_type,
            $this->trade_id,
            $this->service_zone_id,
            $this->min_duration_days,
        ]));
    }

    /** LE TAUX EN FRACTION — ce que le partage attend. */
    public function taux(): float
    {
        return max(0.0, min(1.0, ((float) $this->percent) / 100));
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActives(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** UNE RÈGLE DATÉE NE VAUT QUE DANS SA FENÊTRE : hors saison, elle n'existe pas. */
    public function couvre(CarbonInterface $date): bool
    {
        if ($this->starts_on !== null && $date->lt($this->starts_on->startOfDay())) {
            return false;
        }

        return ! ($this->ends_on !== null && $date->gt($this->ends_on->endOfDay()));
    }

    public function libelleDuCas(): string
    {
        $morceaux = array_filter([
            $this->module ? (self::MODULES[$this->module] ?? $this->module) : null,
            $this->asset_type,
            $this->trade?->name,
            $this->serviceZone?->name,
            $this->min_duration_days ? 'à partir de '.$this->min_duration_days.' jours' : null,
        ]);

        return $morceaux === [] ? 'Toute la plateforme' : implode(' · ', $morceaux);
    }
}
