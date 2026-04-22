<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'region_id',
        'province_id',
        'commune_id',
        'postal_code_id',
        'name',
        'legal_name',
        'slug',
        'type',
        'tva_number',
        'email',
        'phone',
        'billing_email',
        'status',
        'address_line_1',
        'address_line_2',
        'city',
        'postal_code',
        'is_multisite',
        'is_key_account',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'is_multisite' => 'boolean',
        'is_key_account' => 'boolean',
        'metadata' => 'array',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    public function postalCodeReference(): BelongsTo
    {
        return $this->belongsTo(PostalCode::class, 'postal_code_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(OrganizationSite::class);
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(RendezVous::class);
    }

    public function fieldTeams(): HasMany
    {
        return $this->hasMany(FieldTeam::class);
    }


    public function getPriorityZoneIdsAttribute(): array
    {
        $ids = (array) data_get($this->metadata, 'priority_zone_ids', []);

        if ($ids === [] && filled(data_get($this->metadata, 'priority_zone_id'))) {
            $ids = [(int) data_get($this->metadata, 'priority_zone_id')];
        }

        return collect($ids)->map(fn ($id) => (int) $id)->values()->all();
    }

    public function bookingPolicy(): array
    {
        return [
            'approval_mode' => (string) data_get($this->metadata, 'approval_mode', 'auto'),
            'purchase_order_required' => (bool) data_get($this->metadata, 'purchase_order_required', false),
            'default_cost_center' => data_get($this->metadata, 'default_cost_center'),
            'negotiated_discount_percent' => data_get($this->metadata, 'negotiated_discount_percent'),
            'priority_zone_id' => data_get($this->metadata, 'priority_zone_id'),
            'priority_zone_ids' => $this->priority_zone_ids,
            'contract_reference' => data_get($this->metadata, 'contract_reference'),
            'pricing_profile' => data_get($this->metadata, 'pricing_profile'),
            'sla_hours' => data_get($this->metadata, 'sla_hours'),
        ];
    }
}
