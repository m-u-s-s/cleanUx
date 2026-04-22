<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_account_id',
        'country_id',
        'service_zone_id',
        'default_field_team_id',
        'default_service_partner_id',
        'contract_reference',
        'status',
        'pricing_model',
        'billing_cycle',
        'effective_from',
        'effective_to',
        'approval_mode',
        'requires_purchase_order',
        'default_cost_center',
        'negotiated_discount_percent',
        'payment_terms_days',
        'sla_response_hours',
        'sla_resolution_hours',
        'allowed_service_catalog_ids',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'requires_purchase_order' => 'boolean',
        'negotiated_discount_percent' => 'decimal:2',
        'payment_terms_days' => 'integer',
        'sla_response_hours' => 'integer',
        'sla_resolution_hours' => 'integer',
        'allowed_service_catalog_ids' => 'array',
        'metadata' => 'array',
    ];

    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    public function defaultFieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class, 'default_field_team_id');
    }

    public function defaultServicePartner(): BelongsTo
    {
        return $this->belongsTo(ServicePartner::class, 'default_service_partner_id');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(EnterpriseWorkOrder::class);
    }

    public function isActiveOn(?\DateTimeInterface $date = null): bool
    {
        $date = $date ? now()->parse($date) : now();

        if (! in_array($this->status, ['active', 'signed', 'pilot'], true)) {
            return false;
        }

        if ($this->effective_from && $date->lt($this->effective_from)) {
            return false;
        }

        if ($this->effective_to && $date->gt($this->effective_to)) {
            return false;
        }

        return true;
    }
}
