<?php

namespace App\Models;

use Database\Factories\FieldTeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldTeam extends Model
{
    /** @use HasFactory<FieldTeamFactory> */
    use HasFactory;

    protected $fillable = [
        'country_id',
        'service_zone_id',
        'organization_account_id',
        'provider_agency_id',
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

    /** @return BelongsTo<OrganizationAccount, $this> */
    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class);
    }

    /** @return BelongsTo<ServicePartner, $this> */
    public function servicePartner(): BelongsTo
    {
        return $this->belongsTo(ServicePartner::class);
    }

    /** @return BelongsTo<User, $this> */
    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_user_id');
    }

    /** @return HasMany<FieldTeamMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(FieldTeamMember::class);
    }

    /**
     * Les membres encore dans l'équipe.
     *
     * @return HasMany<FieldTeamMember, $this>
     */
    public function activeMembers(): HasMany
    {
        return $this->members()->where('is_active', true)->whereNull('left_at');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'field_team_members')
            ->withPivot(['role_on_team', 'is_team_lead', 'is_active', 'joined_at', 'left_at', 'metadata'])
            ->withTimestamps();
    }

    /** @return HasMany<MissionTeamAssignment, $this> */
    public function missionAssignments(): HasMany
    {
        return $this->hasMany(MissionTeamAssignment::class);
    }

    /** @return HasMany<OrganizationContract, $this> */
    public function organizationContracts(): HasMany
    {
        return $this->hasMany(OrganizationContract::class, 'default_field_team_id');
    }

    /** @return HasMany<EnterpriseWorkOrder, $this> */
    public function enterpriseWorkOrders(): HasMany
    {
        return $this->hasMany(EnterpriseWorkOrder::class, 'assigned_field_team_id');
    }

    /** @return HasMany<MissionBatch, $this> */
    public function missionBatches(): HasMany
    {
        return $this->hasMany(MissionBatch::class, 'assigned_field_team_id');
    }
}
