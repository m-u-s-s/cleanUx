<?php

namespace App\Models;

use Database\Factories\PostalCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PostalCode extends Model
{
    /** @use HasFactory<PostalCodeFactory> */
    use HasFactory;

    protected $fillable = [
        'country_id',
        'region_id',
        'province_id',
        'commune_id',
        'code',
        'city_name',
        'latitude',
        'longitude',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<Region, $this> */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    /** @return BelongsTo<Province, $this> */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    /** @return BelongsTo<Commune, $this> */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /** @return BelongsToMany<ServiceZone, $this> */
    public function serviceZones(): BelongsToMany
    {
        return $this->belongsToMany(ServiceZone::class, 'service_zone_postal_code')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<Booking, $this> */
    public function rendezVous(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
