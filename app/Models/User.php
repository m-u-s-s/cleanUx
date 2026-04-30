<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use Billable;

    public const ROLE_CLIENT = 'client';
    public const ROLE_EMPLOYE = 'employe';
    public const ROLE_ENTREPRISE = 'entreprise';
    public const ROLE_ADMIN = 'admin';

    public const ACCESS_SCOPE_ALL = 'all';
    public const ACCESS_SCOPE_ZONE = 'zone';
    public const ACCESS_SCOPE_READONLY = 'readonly';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'access_scope',
        'managed_service_zone_id',
        'permissions',
        'tva_number',
        'duree_creneau',
        'plan_type',
        'plan_status',
        'premium_started_at',
        'premium_renewal_at',
        'organization_account_id',
        'postal_code_id',
        'primary_service_zone_id',
        'phone',
        'locale',
        'timezone',
        'status',
        'is_active',
        'metadata',
        'current_lat',
        'current_lng',
        'last_location_at',
        'stripe_connect_account_id',
        'stripe_connect_status',
        'stripe_connect_onboarded_at',
        'stripe_connect_charges_enabled_at',
        'stripe_connect_payouts_enabled_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'duree_creneau' => 'integer',
        'premium_started_at' => 'datetime',
        'premium_renewal_at' => 'datetime',
        'is_active' => 'boolean',
        'permissions' => 'array',
        'metadata' => 'array',
        'current_lat' => 'decimal:7',
        'current_lng' => 'decimal:7',
        'last_location_at' => 'datetime',
        'stripe_connect_onboarded_at' => 'datetime',
        'stripe_connect_charges_enabled_at' => 'datetime',
        'stripe_connect_payouts_enabled_at' => 'datetime',
    ];

    protected $appends = [
        'profile_photo_url',
        'is_admin',
        'role_label',
        'admin_access_scope_label',
    ];

    public static function clientRoleValues(): array
    {
        return [self::ROLE_CLIENT, self::ROLE_ENTREPRISE];
    }

    public static function allowedAdminPermissions(): array
    {
        return [
            'manage-calendar' => 'Agenda',
            'manage-users' => 'Utilisateurs',
            'manage-services' => 'Services',
            'manage-entreprises' => 'Entreprises',
            'manage-finance' => 'Finance',
            'manage-analytics' => 'Analytics',
            'manage-quality' => 'Qualité',
            'manage-premium' => 'Premium',
            'manage-audit-logs' => 'Audit logs',
            'manage-modules' => 'Modules',
            'perform-critical-admin-actions' => 'Actions critiques',
        ];
    }

    public function scopeClientFacing(Builder $query): Builder
    {
        return $query->whereIn('role', self::clientRoleValues());
    }

    public function matchesRole(string $role): bool
    {
        return match ($role) {
            self::ROLE_CLIENT => in_array($this->role, self::clientRoleValues(), true),
            default => $this->role === $role,
        };
    }

    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    public function postalCode(): BelongsTo
    {
        return $this->belongsTo(PostalCode::class);
    }

    public function primaryServiceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class, 'primary_service_zone_id');
    }

    public function managedServiceZone(): BelongsTo
    {
        return $this->belongsTo(ServiceZone::class, 'managed_service_zone_id');
    }

    public function disponibilites(): HasMany
    {
        return $this->hasMany(Disponibilite::class, 'user_id');
    }

    public function rendezVousEmploye(): HasMany
    {
        return $this->hasMany(RendezVous::class, 'employe_id');
    }

    public function rendezVousClient(): HasMany
    {
        return $this->hasMany(RendezVous::class, 'client_id');
    }

    public function favoriteEmployes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_employee_preferences', 'client_id', 'employe_id')
            ->withPivot('is_favorite')
            ->withTimestamps();
    }

    public function preferredByClients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'client_employee_preferences', 'employe_id', 'client_id')
            ->withPivot('is_favorite')
            ->withTimestamps();
    }

    public function zoneAssignments(): HasMany
    {
        return $this->hasMany(EmployeeZoneAssignment::class);
    }

    public function fieldTeamMemberships(): HasMany
    {
        return $this->hasMany(FieldTeamMember::class);
    }

    public function activeFieldTeamMemberships(): HasMany
    {
        return $this->fieldTeamMemberships()->where('is_active', true)->whereNull('left_at');
    }

    public function fieldTeams(): BelongsToMany
    {
        return $this->belongsToMany(FieldTeam::class, 'field_team_members')
            ->withPivot(['role_on_team', 'is_team_lead', 'is_active', 'joined_at', 'left_at', 'metadata'])
            ->withTimestamps();
    }

    public function ledFieldTeams(): HasMany
    {
        return $this->hasMany(FieldTeam::class, 'team_lead_user_id');
    }

    public function activeLedFieldTeams(): HasMany
    {
        return $this->ledFieldTeams()->where('status', 'active');
    }

    public function latestActiveFieldTeamMembership(): HasOne
    {
        return $this->hasOne(FieldTeamMember::class)->where('is_active', true)->whereNull('left_at')->latestOfMany();
    }

    public function serviceZones(): BelongsToMany
    {
        return $this->belongsToMany(ServiceZone::class, 'employee_zone_assignments')
            ->withPivot(['assignment_type', 'coverage_priority', 'is_active', 'starts_at', 'ends_at', 'notes'])
            ->withTimestamps();
    }

    public function hasStripeConnectAccount(): bool
    {
        return filled($this->stripe_connect_account_id);
    }

    public function canReceiveStripeConnectPayments(): bool
    {
        return filled($this->stripe_connect_account_id)
            && $this->stripe_connect_status === 'active'
            && filled($this->stripe_connect_charges_enabled_at)
            && filled($this->stripe_connect_payouts_enabled_at);
    }

    public function organizationSites(): HasMany
    {
        return $this->hasMany(OrganizationSite::class, 'client_user_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class, 'client_id');
    }

    public function permissionList(): array
    {
        return array_values(array_filter((array) ($this->permissions ?? [])));
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->isAdmin()) {
            return false;
        }

        $permissions = $this->permissionList();

        return $permissions === []
            || in_array('*', $permissions, true)
            || in_array($permission, $permissions, true);
    }

    public function isZoneScopedAdmin(): bool
    {
        return $this->isAdmin()
            && $this->access_scope === self::ACCESS_SCOPE_ZONE
            && ! empty($this->managed_service_zone_id);
    }

    public function isReadOnlyAdmin(): bool
    {
        return $this->isAdmin() && $this->access_scope === self::ACCESS_SCOPE_READONLY;
    }

    public function canAccessAdminModule(string $permission): bool
    {
        if (! $this->isAdmin() || ! $this->is_active) {
            return false;
        }

        return $this->hasPermission($permission);
    }

    public function canPerformCriticalAdminActions(): bool
    {
        return $this->canAccessAdminModule('perform-critical-admin-actions')
            && ! $this->isReadOnlyAdmin();
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isEmploye(): bool
    {
        return $this->role === self::ROLE_EMPLOYE;
    }

    public function isEntreprise(): bool
    {
        return $this->role === self::ROLE_ENTREPRISE;
    }

    public function isOrganizationCustomer(): bool
    {
        return $this->isEntreprise();
    }

    public function hasOrganizationContext(): bool
    {
        return $this->isOrganizationCustomer() && filled($this->organization_account_id);
    }

    public function isClient(): bool
    {
        return in_array($this->role, self::clientRoleValues(), true);
    }

    public function isPremium(): bool
    {
        return $this->isClient()
            && $this->plan_type === 'premium'
            && $this->plan_status === 'active';
    }

    public function isPremiumPastDue(): bool
    {
        return $this->plan_type === 'premium' && $this->plan_status === 'past_due';
    }

    public function isStandard(): bool
    {
        return $this->isClient() && $this->plan_type === 'standard';
    }

    public function canChooseEmployee(): bool
    {
        return $this->isPremium();
    }

    public function canViewEmployeeAvailability(): bool
    {
        return $this->isPremium();
    }

    public function hasBillingIssue(): bool
    {
        return $this->isPremiumPastDue();
    }

    public function getIsAdminAttribute(): bool
    {
        return $this->isAdmin();
    }

    public function getRoleLabelAttribute(): string
    {
        return match ($this->role) {
            self::ROLE_CLIENT => 'Client',
            self::ROLE_EMPLOYE => 'Employé',
            self::ROLE_ENTREPRISE => 'Entreprise',
            self::ROLE_ADMIN => 'Admin',
            default => ucfirst((string) $this->role),
        };
    }

    public function getAdminAccessScopeLabelAttribute(): string
    {
        return match ($this->access_scope ?? self::ACCESS_SCOPE_ALL) {
            self::ACCESS_SCOPE_ZONE => 'Zone',
            self::ACCESS_SCOPE_READONLY => 'Lecture seule',
            default => 'Global',
        };
    }

    public function isFieldTeamLead(): bool
    {
        return $this->activeLedFieldTeams()->exists()
            || $this->activeFieldTeamMemberships()->where('is_team_lead', true)->exists();
    }

    public function activeFieldTeamIds(): array
    {
        return $this->activeFieldTeamMemberships()->pluck('field_team_id')->map(fn($id) => (int) $id)->values()->all();
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // mission


    public function leadMissions(): HasMany
    {
        return $this->hasMany(Mission::class, 'lead_employee_id');
    }

    public function startedMissions(): HasMany
    {
        return $this->hasMany(Mission::class, 'started_by_user_id');
    }

    public function closedMissions(): HasMany
    {
        return $this->hasMany(Mission::class, 'closed_by_user_id');
    }

    public function missionAssignments(): HasMany
    {
        return $this->hasMany(MissionAssignment::class);
    }

    public function validatedMissionCodes(): HasMany
    {
        return $this->hasMany(MissionVerificationCode::class, 'validated_by_user_id');
    }

    public function customerCredits(): HasMany
    {
        return $this->hasMany(CustomerCredit::class, 'client_id');
    }

    public function activeCreditBalance(): float
    {
        return (float) $this->customerCredits()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->sum('remaining_amount');
    }
}
