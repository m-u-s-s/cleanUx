<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientSubscription extends Model
{
    protected $fillable = [
        'client_id',
        'plan_id',
        'service_zone_id',
        'service_catalog_id',
        'day_of_week',
        'heure',
        'preferred_employee_id',
        'base_price',
        'discounted_price',
        'start_date',
        'end_date',
        'status',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}