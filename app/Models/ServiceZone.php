<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceZone extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'region_id',
        'province_id',
        'commune_id',
        'parent_zone_id',
        'code',
        'name',
        'slug',
        'coverage_type',
        'status',
        'is_bookable',
        'is_visible',
        'priority',
        'minimum_notice_hours',
        'maximum_daily_jobs',
        'travel_surcharge',
        'time_buffer_minutes',
        'metadata',
        'notes',
        'activated_at',
        'deactivated_at',
    ];

    protected $casts = [
        'is_bookable' => 'boolean',
        'is_visible' => 'boolean',
        'travel_surcharge' => 'decimal:2',
        'metadata' => 'array',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime',
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

    public function parentZone(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_zone_id');
    }

    public function childZones(): HasMany
    {
        return $this->hasMany(self::class, 'parent_zone_id');
    }

    public function postalCodes(): BelongsToMany
    {
        return $this->belongsToMany(PostalCode::class, 'service_zone_postal_code')
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function zoneServiceRules(): HasMany
    {
        return $this->hasMany(ZoneServiceRule::class);
    }

    public function serviceCatalogs(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCatalog::class, 'zone_service_rules')
            ->withPivot([
                'is_enabled',
                'requires_manual_validation',
                'base_price_override',
                'price_multiplier',
                'minimum_notice_hours',
                'maximum_daily_capacity',
                'settings',
            ])
            ->withTimestamps();
    }

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(EmployeeZoneAssignment::class);
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employee_zone_assignments')
            ->withPivot(['assignment_type', 'coverage_priority', 'is_active', 'starts_at', 'ends_at', 'notes'])
            ->withTimestamps();
    }

    public function organizationSites(): HasMany
    {
        return $this->hasMany(OrganizationSite::class);
    }

    public function fieldTeams(): HasMany
    {
        return $this->hasMany(FieldTeam::class);
    }

    public function partnerZoneCoverages(): HasMany
    {
        return $this->hasMany(PartnerZoneCoverage::class);
    }

    public function servicePartners(): BelongsToMany
    {
        return $this->belongsToMany(ServicePartner::class, 'partner_zone_coverages')
            ->withPivot(['service_catalog_id', 'coverage_status', 'priority', 'max_daily_capacity', 'sla_response_hours', 'metadata'])
            ->withTimestamps();
    }

    public function rendezVous(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
