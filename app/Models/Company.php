<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'tva_number',
        'company_number',
        'email',
        'phone',
        'address',
        'postal_code',
        'city',
        'country',
        'lat',
        'lng',
        'google_place_id',
        'stripe_connect_account_id',
        'stripe_connect_status',
        'type',
        'default_slot_duration',
        'max_daily_missions',
        'is_active',
        'logo_path',
        'primary_color',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lng' => 'decimal:7',
        'is_active' => 'boolean',
    ];

    // 🔹 relations
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function employees()
    {
        return $this->hasMany(User::class)->where('role', 'employe');
    }

    public function clients()
    {
        return $this->hasMany(User::class)->where('role', 'client');
    }

    public function rendezVous()
    {
        return $this->hasMany(\App\Models\RendezVous::class);
    }
}