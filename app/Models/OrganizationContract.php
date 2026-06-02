<?php

namespace App\Models;

use Database\Factories\OrganizationContractFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * OrganizationContract — contrat-cadre B2B entre une organisation cliente et
 * (optionnellement) une organisation prestataire : tarifs négociés, SLA,
 * politique d'approbation et fenêtre de validité.
 *
 * @property int $id
 * @property int $organization_account_id
 * @property int|null $provider_organization_id
 * @property int|null $country_id
 * @property int|null $service_zone_id
 * @property int|null $default_field_team_id
 * @property int|null $default_service_partner_id
 * @property string|null $contract_reference
 * @property string $status
 * @property string|null $pricing_model
 * @property string|null $billing_cycle
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_to
 * @property string|null $approval_mode
 * @property bool $requires_purchase_order
 * @property string|null $default_cost_center
 * @property float|null $negotiated_discount_percent
 * @property int|null $payment_terms_days
 * @property int|null $sla_response_hours
 * @property int|null $sla_resolution_hours
 * @property array<int, int>|null $allowed_service_catalog_ids
 * @property array<string, mixed>|null $metadata
 * @property string|null $notes
 * @property-read OrganizationAccount|null $providerOrganization
 * @property-read Collection<int, ContractRateCard> $rateCards
 * @property-read Collection<int, ContractSlaEvent> $slaEvents
 */
class OrganizationContract extends Model
{
    /** @use HasFactory<OrganizationContractFactory> */
    use HasFactory;

    protected $fillable = [
        'organization_account_id',
        'provider_organization_id',
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

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class);
    }

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function providerOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'provider_organization_id');
    }

    /** @return HasMany<ContractRateCard, $this> */
    public function rateCards(): HasMany
    {
        return $this->hasMany(ContractRateCard::class);
    }

    /** @return HasMany<ContractSlaEvent, $this> */
    public function slaEvents(): HasMany
    {
        return $this->hasMany(ContractSlaEvent::class, 'organization_contract_id');
    }

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return BelongsTo<ServiceZone, $this> */
    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    /** @return BelongsTo<FieldTeam, $this> */
    public function defaultFieldTeam(): BelongsTo
    {
        return $this->belongsTo(FieldTeam::class, 'default_field_team_id');
    }

    /** @return BelongsTo<ServicePartner, $this> */
    public function defaultServicePartner(): BelongsTo
    {
        return $this->belongsTo(ServicePartner::class, 'default_service_partner_id');
    }

    /** @return HasMany<EnterpriseWorkOrder, $this> */
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
