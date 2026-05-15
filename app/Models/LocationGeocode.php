<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LocationGeocode extends Model
{
    use HasFactory;

    protected $fillable = [
        'lookup_hash',
        'address_line',
        'postal_code',
        'city',
        'country_code',
        'address',
        'address_line',
        'address_hash',
        'lookup_hash',
        'postal_code',
        'city',
        'country_code',
        'lat',
        'lng',
        'provider',
        'raw',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'raw' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $geocode): void {
            $address = $geocode->address
                ?? $geocode->address_line
                ?? trim(implode(' ', array_filter([
                    $geocode->postal_code ?? null,
                    $geocode->city ?? null,
                    $geocode->country_code ?? null,
                ])));

            if (blank($geocode->address)) {
                $geocode->address = $address;
            }

            if (blank($geocode->address_line)) {
                $geocode->address_line = $address;
            }

            $hash = $geocode->lookup_hash
                ?? $geocode->address_hash
                ?? sha1($address . '|' . ($geocode->postal_code ?? '') . '|' . ($geocode->city ?? ''));

            if (blank($geocode->lookup_hash)) {
                $geocode->lookup_hash = $hash;
            }

            if (blank($geocode->address_hash)) {
                $geocode->address_hash = $hash;
            }
        });
    }
}
