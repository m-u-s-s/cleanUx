<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_id',
        'service_zone_id',
        'organization_account_id',
        'service_partner_id',
        'team_lead_user_id',
        'name',
        'slug',
        'status',
        'is_internal',
        'max_concurrent_missions',
        'metadata',
        'notes',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'max_concurrent_missions' => 'integer',
        'metadata' => 'array',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function serviceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class);
    }

    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class);
    }

    public function servicePartner(): BelongsTo
    {
        return $this->belongsTo(ServicePartner::class);
    }

    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(FieldTeamMember::class);
    }

    public function activeMembers(): HasMany
    {
        return $this->members()->where('is_active', true)->whereNull('left_at');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'field_team_members')
            ->withPivot(['role_on_team', 'is_team_lead', 'is_active', 'joined_at', 'left_at', 'metadata'])
            ->withTimestamps();
    }

    public function missionAssignments(): HasMany
    {
        return $this->hasMany(MissionTeamAssignment::class);
    }


    public function organizationContracts(): HasMany
    {
        return $this->hasMany(OrganizationContract::class, 'default_field_team_id');
    }

    public function enterpriseWorkOrders(): HasMany
    {
        return $this->hasMany(EnterpriseWorkOrder::class, 'assigned_field_team_id');
    }

    public function missionBatches(): HasMany
    {
        return $this->hasMany(MissionBatch::class, 'assigned_field_team_id');
    }

}
