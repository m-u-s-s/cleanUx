<?php

namespace App\Models\Concerns;

use App\Models\AvailabilityException;
use App\Models\AvailabilitySlot;
use App\Models\EmployeeZoneAssignment;
use App\Models\FieldTeam;
use App\Models\Mission;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

trait HasProviderFeatures
{
    /** @return HasOne<AvailabilitySlot, $this> */
    public function providerProfile(): HasOne
    {
        return $this->hasOne(ProviderProfile::class);
    }

    /** @return HasMany<AvailabilitySlot, $this> */
    public function availabilitySlots(): HasMany
    {
        return $this->hasMany(AvailabilitySlot::class, 'provider_user_id');
    }

    /** @return HasMany<AvailabilityException, $this> */
    public function availabilityExceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class, 'provider_user_id');
    }

    /** @return BelongsToMany<EmployeeZoneAssignment, $this> */
    public function trades(): BelongsToMany
    {
        return $this->belongsToMany(Trade::class, 'trade_user')
            ->withPivot(['is_primary', 'proficiency', 'notes'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<EmployeeZoneAssignment, $this> */
    public function serviceZones(): BelongsToMany
    {
        return $this->belongsToMany(
            ServiceZone::class,
            'employee_zone_assignments',
            'user_id',
            'service_zone_id'
        )->withPivot([
            'assignment_type',
            'coverage_priority',
            'is_active',
            'starts_at',
            'ends_at',
            'notes',
        ])->withTimestamps();
    }

    /** @return HasMany<ServiceZone, $this> */
    public function zoneAssignments(): HasMany
    {
        return $this->hasMany(EmployeeZoneAssignment::class, 'user_id');
    }

    /** @return BelongsToMany<ServiceZone, $this> */
    public function fieldTeams(): BelongsToMany
    {
        return $this->belongsToMany(FieldTeam::class, 'field_team_members')
            ->withPivot(['role_on_team', 'is_team_lead', 'is_active', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /** @return BelongsTo<ServiceZone, $this> */
    public function managedServiceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class, 'managed_service_zone_id');
    }

    /** @return BelongsTo<ServiceZone, $this> */
    public function primaryServiceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class, 'primary_service_zone_id');
    }

    public function activeServiceZones(): BelongsToMany
    {
        return $this->serviceZones()
            ->wherePivot('is_active', true);
    }

    public function leadMissions()
    {
        $query = Mission::query();

        if (Schema::hasColumn('missions', 'team_lead_user_id')) {
            return $query->where('team_lead_user_id', $this->id);
        }

        if (Schema::hasColumn('missions', 'lead_user_id')) {
            return $query->where('lead_user_id', $this->id);
        }

        if (
            Schema::hasTable('mission_team_assignments')
            && Schema::hasColumn('mission_team_assignments', 'mission_id')
            && Schema::hasColumn('mission_team_assignments', 'user_id')
        ) {
            return $query->whereIn('id', function ($sub) {
                $sub->from('mission_team_assignments')
                    ->select('mission_id')
                    ->where('user_id', $this->id);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    public function activeLedFieldTeams()
    {
        $query = FieldTeam::query();

        if (Schema::hasColumn('field_teams', 'lead_user_id')) {
            return $query->where('lead_user_id', $this->id)
                ->when(Schema::hasColumn('field_teams', 'is_active'), fn ($q) => $q->where('is_active', true));
        }

        if (Schema::hasColumn('field_teams', 'team_lead_user_id')) {
            return $query->where('team_lead_user_id', $this->id)
                ->when(Schema::hasColumn('field_teams', 'is_active'), fn ($q) => $q->where('is_active', true));
        }

        if (
            Schema::hasTable('field_team_members')
            && Schema::hasColumn('field_team_members', 'field_team_id')
            && Schema::hasColumn('field_team_members', 'user_id')
        ) {
            return $query->whereIn('id', function ($sub) {
                $sub->from('field_team_members')
                    ->select('field_team_id')
                    ->where('user_id', $this->id)
                    ->where(function ($q) {
                        if (Schema::hasColumn('field_team_members', 'role')) {
                            $q->whereIn('role', ['lead', 'leader', 'team_lead']);
                        }
                    });
            })->when(Schema::hasColumn('field_teams', 'is_active'), fn ($q) => $q->where('is_active', true));
        }

        return $query->whereRaw('1 = 0');
    }

    public function isFieldTeamLead(): bool
    {
        return $this->activeLedFieldTeams()->exists();
    }

    /** @return BelongsToMany<self, $this> */
    public function preferredByClients(): BelongsToMany
    {
        if (! Schema::hasTable('client_provider_preferences')) {
            return $this->belongsToMany(
                self::class,
                'client_provider_preferences',
                'provider_user_id',
                'client_user_id'
            )->whereRaw('1 = 0');
        }

        return $this->belongsToMany(
            self::class,
            'client_provider_preferences',
            'provider_user_id',
            'client_user_id'
        )->withTimestamps();
    }

    public function canReceiveStripeConnectPayments(): bool
    {
        $profile = $this->providerProfile;
        if (! $profile) {
            return false;
        }

        return ! empty($profile->stripe_connect_account_id)
            && ($profile->stripe_connect_status === 'active' || $profile->stripe_connect_onboarded_at !== null);
    }

    /**
     * KYC = BLOCAGE STRICT (décision produit 2026-06-11) : un prestataire ne peut
     * recevoir/accepter une mission que si sa vérification d'identité est validée.
     */
    public function hasClearedKyc(): bool
    {
        return (bool) $this->providerProfile?->isVerified();
    }
}
