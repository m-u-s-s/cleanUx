<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

class OrganizationSite extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_account_id',
        'client_user_id',
        'service_zone_id',
        'postal_code_id',
        'name',
        'site_code',
        'contact_name',
        'email',
        'phone',
        'address_line_1',
        'address_line_2',
        'city',
        'postal_code',
        'access_instructions',
        'latitude',
        'longitude',
        'is_primary',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class);
    }

    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    public function postalCodeReference(): BelongsTo
    {
        return $this->belongsTo(PostalCode::class, 'postal_code_id');
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class, 'organization_site_id');
    }

    public function bookingPolicy(): array
    {
        return [
            'approval_mode' => (string) Arr::get($this->metadata, 'approval_mode', 'inherit'),
            'purchase_order_required' => Arr::get($this->metadata, 'purchase_order_required'),
            'default_cost_center' => Arr::get($this->metadata, 'default_cost_center'),
        ];
    }
}
