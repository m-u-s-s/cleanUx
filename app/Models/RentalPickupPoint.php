<?php

namespace App\Models;

use Database\Factories\RentalPickupPointFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** L'AGENCE OÙ LE CLIENT VIENT CHERCHER SA VOITURE. */
class RentalPickupPoint extends Model
{
    /** @use HasFactory<RentalPickupPointFactory> */
    use HasFactory;

    protected $fillable = [
        'name', 'address', 'postal_code', 'city', 'country_code',
        'lat', 'lng', 'opening_hours', 'instructions', 'phone',
        'is_active', 'sort_order',
    ];

    protected $casts = [
        'opening_hours' => 'array',
        'is_active' => 'boolean',
        'lat' => 'float',
        'lng' => 'float',
        'sort_order' => 'integer',
    ];

    /** @return HasMany<RentalVehicle, $this> */
    public function vehicles(): HasMany
    {
        return $this->hasMany(RentalVehicle::class, 'pickup_point_id');
    }

    /** @param  Builder<RentalPickupPoint>  $query */
    public function scopeActif(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** L'adresse d'un seul tenant, telle qu'on la met sur une confirmation. */
    public function adresseComplete(): string
    {
        return trim(implode(', ', array_filter([
            $this->address,
            trim(($this->postal_code ?? '').' '.($this->city ?? '')),
        ])));
    }
}
