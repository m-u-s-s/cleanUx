<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'code',
        'name',
        'slug',
        'type',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }

    public function communes(): HasMany
    {
        return $this->hasMany(Commune::class);
    }

    public function postalCodes(): HasMany
    {
        return $this->hasMany(PostalCode::class);
    }

    public function serviceZones(): HasMany
    {
        return $this->hasMany(ServiceZone::class);
    }
}
