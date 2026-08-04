<?php

namespace App\Models;

use Database\Factories\CountryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Country extends Model
{
    /** @use HasFactory<CountryFactory> */
    use HasFactory;

    protected $fillable = [
        'iso_code',
        'iso3_code',
        'name',
        'official_name',
        'default_locale',
        'currency_code',
        'phone_code',
        'timezone',
        'is_active',
        /*
         * Ces trois colonnes existaient en base sans être assignables en masse.
         *
         * Un `Country::create([... 'booking_enabled' => true])` les perdait EN SILENCE : aucune
         * erreur, et un pays qui s'affiche « réservations fermées » sans qu'on comprenne pourquoi.
         * C'est le mode d'échec propre à `$fillable` — il refuse sans le dire.
         */
        'booking_enabled',
        'market_stage',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'booking_enabled' => 'boolean',
        'settings' => 'array',
    ];

    /** @return HasMany<Region, $this> */
    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    /** @return HasMany<Province, $this> */
    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }

    /** @return HasMany<Commune, $this> */
    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }

    /** @return HasMany<PostalCode, $this> */
    public function postalCodes(): HasMany
    {
        return $this->hasMany(PostalCode::class);
    }

    /** @return HasMany<ServiceZone, $this> */
    public function serviceZones(): HasMany
    {
        return $this->hasMany(ServiceZone::class);
    }

    /** @return HasOne<CountryServiceCatalogRule, $this> */
    public function operationalSetting(): HasOne
    {
        return $this->hasOne(CountryOperationalSetting::class);
    }

    /** @return HasOne<CountryServiceCatalogRule, $this> */
    public function billingProfile(): HasOne
    {
        return $this->hasOne(CountryBillingProfile::class);
    }

    /** @return HasOne<CountryServiceCatalogRule, $this> */
    public function launchReadiness(): HasOne
    {
        return $this->hasOne(MarketLaunchReadiness::class);
    }

    /** @return HasMany<CountryServiceCatalogRule, $this> */
    public function countryServiceRules(): HasMany
    {
        return $this->hasMany(CountryServiceCatalogRule::class);
    }

    /** @return HasMany<CountryServiceCatalogRule, $this> */
    public function serviceCatalogRules(): HasMany
    {
        return $this->hasMany(CountryServiceCatalogRule::class);
    }

    /** @return HasOne<MarketLaunchReadiness, $this> */
    public function marketLaunchReadiness(): HasOne
    {
        return $this->hasOne(MarketLaunchReadiness::class);
    }
}
