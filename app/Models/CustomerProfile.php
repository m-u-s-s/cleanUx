<?php

namespace App\Models;

use App\Enums\CustomerType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_type',
        'default_phone',
        'default_address',
        'default_city',
        'default_postal_code',
        'default_country',
        'plan_type',
        'plan_status',
        'premium_started_at',
        'premium_renewal_at',
        'preferences',
    ];

    protected $casts = [
        'customer_type' => CustomerType::class,
        'preferences' => 'array',
        'premium_started_at' => 'datetime',
        'premium_renewal_at' => 'datetime',
    ];

    // Relations
    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class);
    }

    // Helpers
    public function isPersonal(): bool
    {
        return $this->customer_type === CustomerType::PERSONAL;
    }

    public function isCompany(): bool
    {
        return $this->customer_type === CustomerType::COMPANY;
    }

    public function isPremium(): bool
    {
        return $this->plan_type === 'premium' && $this->plan_status === 'active';
    }
}
