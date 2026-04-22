<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Country extends Model
{
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
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function regions(): HasMany { return $this->hasMany(Region::class); }
    public function provinces(): HasMany { return $this->hasMany(Province::class); }
    public function communes(): HasMany { return $this->hasMany(Commune::class); }
    public function postalCodes(): HasMany { return $this->hasMany(PostalCode::class); }
    public function serviceZones(): HasMany { return $this->hasMany(ServiceZone::class); }
    public function operationalSetting(): HasOne { return $this->hasOne(CountryOperationalSetting::class); }
    public function billingProfile(): HasOne { return $this->hasOne(CountryBillingProfile::class); }
    public function serviceCatalogRules(): HasMany { return $this->hasMany(CountryServiceCatalogRule::class); }
    public function marketLaunchReadiness(): HasOne { return $this->hasOne(MarketLaunchReadiness::class); }
}
