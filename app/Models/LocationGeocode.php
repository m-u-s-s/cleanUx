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
}