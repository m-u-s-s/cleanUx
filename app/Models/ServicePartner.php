<?php

namespace App\Models;

use Database\Factories\ServicePartnerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePartner extends Model
{
    /** @use HasFactory<ServicePartnerFactory> */
    use HasFactory;

    protected $fillable = [
        'country_id',
        'name',
        'legal_name',
        'slug',
        'status',
        'email',
        'phone',
        'billing_email',
        'quality_score',
        'is_active',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'quality_score' => 'decimal:2',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    /** @return BelongsTo<Country, $this> */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /** @return HasMany<PartnerZoneCoverage, $this> */
    public function zoneCoverages(): HasMany
    {
        return $this->hasMany(PartnerZoneCoverage::class);
    }

    /** @return BelongsToMany<FieldTeam, $this> */
    public function serviceZones(): BelongsToMany
    {
        return $this->belongsToMany(ServiceZone::class, 'partner_zone_coverages')
            ->withPivot(['service_catalog_id', 'coverage_status', 'priority', 'max_daily_capacity', 'sla_response_hours', 'metadata'])
            ->withTimestamps();
    }

    /** @return HasMany<FieldTeam, $this> */
    public function fieldTeams(): HasMany
    {
        return $this->hasMany(FieldTeam::class);
    }

    /** @return HasMany<MissionPartnerAssignment, $this> */
    public function missionAssignments(): HasMany
    {
        return $this->hasMany(MissionPartnerAssignment::class);
    }

    /** @return HasMany<OrganizationContract, $this> */
    public function organizationContracts(): HasMany
    {
        return $this->hasMany(OrganizationContract::class, 'default_service_partner_id');
    }

    /** @return HasMany<EnterpriseWorkOrder, $this> */
    public function enterpriseWorkOrders(): HasMany
    {
        return $this->hasMany(EnterpriseWorkOrder::class, 'assigned_service_partner_id');
    }

    /** @return HasMany<MissionBatch, $this> */
    public function missionBatches(): HasMany
    {
        return $this->hasMany(MissionBatch::class, 'assigned_service_partner_id');
    }
}
