<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePartner extends Model
{
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

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function zoneCoverages(): HasMany
    {
        return $this->hasMany(PartnerZoneCoverage::class);
    }

    public function serviceZones(): BelongsToMany
    {
        return $this->belongsToMany(ServiceZone::class, 'partner_zone_coverages')
            ->withPivot(['service_catalog_id', 'coverage_status', 'priority', 'max_daily_capacity', 'sla_response_hours', 'metadata'])
            ->withTimestamps();
    }

    public function fieldTeams(): HasMany
    {
        return $this->hasMany(FieldTeam::class);
    }

    public function missionAssignments(): HasMany
    {
        return $this->hasMany(MissionPartnerAssignment::class);
    }
}
