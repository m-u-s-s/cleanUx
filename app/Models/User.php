<?php

namespace App\Models;

use App\Enums\AssistantContextRole;
use App\Enums\CustomerType;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Services\PermissionService;
use App\Models\FieldTeam;
use App\Models\Mission;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Concerns\HasLegacyRoleCompatibility;
use Laravel\Cashier\Billable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use Billable;
    use HasLegacyRoleCompatibility;



    public const ROLE_ADMIN = 'admin';
    public const ROLE_CLIENT = 'client';
    public const ROLE_EMPLOYE = 'employe';
    public const ROLE_EMPLOYEE = 'employe';
    public const ROLE_ENTREPRISE = 'entreprise';
    public const ROLE_PROVIDER = 'provider';

    public const ACCESS_SCOPE_ALL = 'all';
    public const ACCESS_SCOPE_OWN = 'own';
    public const ACCESS_SCOPE_ORGANIZATION = 'organization';

    // ──────────────────────────────────────────────────────
    // Constantes platform_role (rôle global CleanUx)
    // ──────────────────────────────────────────────────────
    public const PLATFORM_USER       = 'user';
    public const PLATFORM_ADMIN      = 'admin';
    public const PLATFORM_SUPER_ADMIN = 'super_admin';

    protected $fillable = [
        'name',
        'email',
        'password',

        'account_type',
        'role',
        'platform_role',

        'phone',
        'tva_number',

        'locale',
        'timezone',
        'status',
        'is_active',

        'current_team_id',
        'current_organization_id',
        'organization_account_id',
        'profile_photo_path',

        'metadata',
        'permissions',

        // Plan / facturation
        'plan_type',
        'plan_status',
        'premium_started_at',
        'premium_renewal_at',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',

        // Sécurité admin
        'access_scope',
        'managed_service_zone_id',
        'is_super_admin',
        'admin_permissions',

        // Onboarding / provider
        'stripe_connect_status',
        'stripe_connect_account_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at'          => 'datetime',
        'password'                   => 'hashed',
        'two_factor_confirmed_at'    => 'datetime',
        'is_active'                  => 'boolean',
        'metadata'                   => 'array',
        'permissions'                => 'array',
    ];

    // ──────────────────────────────────────────────────────
    // Relations
    // ──────────────────────────────────────────────────────

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function providerProfile(): HasOne
    {
        return $this->hasOne(ProviderProfile::class);
    }

    public function currentOrganization(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'current_organization_id');
    }

    public function getOrganizationAccountIdAttribute(): ?int
    {
        return $this->attributes['organization_account_id']
            ?? $this->attributes['current_organization_id']
            ?? null;
    }

    public function organizationMemberships(): HasMany
    {
        return $this->hasMany(OrganizationMember::class);
    }

    public function assistantConversations(): HasMany
    {
        return $this->hasMany(AssistantConversation::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'client_user_id');
    }

    // ──────────────────────────────────────────────────────
    // Helpers : type d'utilisateur
    // ──────────────────────────────────────────────────────

    public function isCustomer(): bool
    {
        return $this->customerProfile()->exists();
    }

    public function isStandard(): bool
    {
        return $this->plan_type === 'standard';
    }

    public function isProvider(): bool
    {
        return $this->providerProfile()->exists();
    }

    public function isClientPersonal(): bool
    {
        return $this->customerProfile?->customer_type === CustomerType::PERSONAL->value;
    }

    public function isClientCompany(): bool
    {
        if ($this->customerProfile?->customer_type === CustomerType::COMPANY->value) {
            return true;
        }

        return ! empty($this->organization_account_id);
    }

    public function isProviderIndependent(): bool
    {
        return $this->providerProfile?->provider_type === ProviderType::INDEPENDENT->value;
    }

    public function isProviderCompanyWorker(): bool
    {
        return $this->providerProfile?->provider_type === ProviderType::COMPANY_WORKER->value;
    }

    public function isPlatformAdmin(): bool
    {
        return in_array($this->platform_role, [self::PLATFORM_ADMIN, self::PLATFORM_SUPER_ADMIN], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->platform_role === self::PLATFORM_SUPER_ADMIN;
    }

    

    // ──────────────────────────────────────────────────────
    // Helpers : organisation courante
    // ──────────────────────────────────────────────────────

    /**
     * Retourne le OrganizationMember de l'utilisateur
     * dans l'organisation courante (ou celle passée en paramètre).
     */
    public function membershipIn(?OrganizationAccount $org = null): ?OrganizationMember
    {
        $orgId = $org?->id ?? $this->current_organization_id;

        if (! $orgId) {
            return null;
        }

        return $this->organizationMemberships()
            ->where('organization_account_id', $orgId)
            ->where('status', 'active')
            ->first();
    }

    public function roleIn(?OrganizationAccount $org = null): ?OrganizationRole
    {
        return $this->membershipIn($org)?->role;
    }

    // ──────────────────────────────────────────────────────
    // Helpers : permissions
    // ──────────────────────────────────────────────────────

    public function canDoInOrg(string $permission, OrganizationAccount|int $org): bool
    {
        return app(PermissionService::class)->can($this, $permission, $org);
    }

    // ──────────────────────────────────────────────────────
    // Helpers : contexte chatbot
    // ──────────────────────────────────────────────────────

    public function assistantContextRole(): AssistantContextRole
    {
        if ($this->isPlatformAdmin()) {
            return AssistantContextRole::ADMIN;
        }

        if ($this->isClientCompany()) {
            return AssistantContextRole::CLIENT_COMPANY;
        }

        if ($this->isClientPersonal()) {
            return AssistantContextRole::CLIENT_PERSONAL;
        }

        if ($this->isProviderCompanyWorker()) {
            return AssistantContextRole::PROVIDER_COMPANY;
        }

        if ($this->isProviderIndependent()) {
            return AssistantContextRole::PROVIDER_INDEPENDENT;
        }

        return AssistantContextRole::CLIENT_PERSONAL; // fallback
    }

    // ──────────────────────────────────────────────────────
    // Helpers : redirections
    // ──────────────────────────────────────────────────────

    public function homeDashboardRoute(): string
    {
        if ($this->isPlatformAdmin()) {
            return 'admin.dashboard';
        }

        if ($this->isClientCompany()) {
            return 'client-company.dashboard';
        }

        if ($this->isClientPersonal()) {
            return 'client.dashboard';
        }

        if ($this->isProviderCompanyWorker()) {
            return 'provider-company.dashboard';
        }

        if ($this->isProviderIndependent()) {
            return 'employe.dashboard';
        }

        return 'dashboard';
    }

    public function canAccessAdminModule(?string $permission = null): bool
    {
        $isAdmin = in_array($this->role, ['admin', 'super_admin'], true)
            || in_array($this->platform_role, ['admin', 'super_admin'], true);

        if (! $isAdmin) {
            return false;
        }

        if (isset($this->is_active) && ! $this->is_active) {
            return false;
        }

        if (($this->role ?? null) === 'super_admin'
            || ($this->platform_role ?? null) === 'super_admin'
            || ($this->is_super_admin ?? false)) {
            return true;
        }

        if ($permission === null) {
            return true;
        }

        $permissions = $this->permissions ?? [];

        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [$permissions];
        }

        if ($permissions instanceof \Illuminate\Support\Collection) {
            $permissions = $permissions->all();
        }

        if (! is_array($permissions)) {
            $permissions = [];
        }

        $aliases = [$permission];

        $aliasMap = [
            'manage-modules' => ['manage_modules', 'admin.modules', 'modules.manage', 'platform.modules.manage', 'platform_modules.manage'],
        ];

        if (isset($aliasMap[$permission])) {
            $aliases = array_merge($aliases, $aliasMap[$permission]);
        }

        foreach ($aliases as $alias) {
            if (array_key_exists($alias, $permissions) && (bool) $permissions[$alias]) {
                return true;
            }

            if (in_array($alias, $permissions, true)) {
                return true;
            }
        }

        return false;
    }

    public function favoriteEmployees()
    {
        return $this->favoriteEmployes();
    }

    public function managedServiceZone()
    {
        return $this->belongsTo(\App\Models\ServiceZone::class, 'managed_service_zone_id');
    }

    public function activeServiceZones()
    {
        return $this->serviceZones()
            ->wherePivot('is_active', true);
    }


    public function isPremium(): bool
    {
        return ($this->plan_type ?? 'standard') === 'premium'
            && in_array(($this->plan_status ?? 'inactive'), ['active', 'trialing', 'paid'], true);
    }

    public static function allowedAdminPermissions(): array
    {
        return [
            'manage-calendar'                 => 'Gestion calendrier',
            'manage-users'                    => 'Gestion utilisateurs',
            'manage-services'                 => 'Gestion services',
            'manage-entreprises'              => 'Gestion entreprises',
            'manage-finance'                  => 'Gestion finance',
            'manage-analytics'                => 'Analytics',
            'manage-quality'                  => 'Qualité',
            'manage-premium'                  => 'Clients premium',
            'manage-audit-logs'               => 'Logs d\'audit',
            'manage-modules'                  => 'Modules plateforme',
            'manage-international'            => 'Opérations internationales',
            'manage-orchestration'            => 'Orchestration terrain',
            'manage-automation'               => 'Automatisation',
            'perform-critical-admin-actions'  => 'Actions critiques',
        ];
    }

    public function hasAdminPermission(string $permission): bool
    {
        if ($this->is_super_admin ?? false) {
            return true;
        }

        $permissions = $this->permissions ?? [];

        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        if ($permissions instanceof \Illuminate\Support\Collection) {
            $permissions = $permissions->all();
        }

        if (! is_array($permissions)) {
            return false;
        }

        if (in_array($permission, $permissions, true)) {
            return true;
        }

        return array_key_exists($permission, $permissions) && (bool) $permissions[$permission];
    }

    public function canPerformCriticalAdminActions(): bool
    {
        return $this->canAccessAdminModule('perform-critical-admin-actions') && ! $this->isReadOnlyAdmin();
    }

    public function permissionList(): array
    {
        $permissions = $this->permissions ?? [];

        if (is_string($permissions)) {
            $decoded = json_decode($permissions, true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        if ($permissions instanceof \Illuminate\Support\Collection) {
            $permissions = $permissions->all();
        }

        return is_array($permissions) ? array_values((array) $permissions) : [];
    }

    public function hasBillingIssue(): bool
    {
        $status = $this->plan_status ?? null;

        return in_array($status, ['past_due', 'unpaid', 'incomplete', 'incomplete_expired'], true);
    }

    public function favoriteEmployes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'provider_favorites',
            'customer_user_id',
            'provider_user_id'
        )->withPivot(['is_favorite', 'status'])->withTimestamps();
    }

    public function zoneAssignments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\EmployeeZoneAssignment::class, 'user_id');
    }

    public function serviceZones(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\ServiceZone::class,
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

    public function fieldTeams(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\FieldTeam::class, 'field_team_members')
            ->withPivot(['role_on_team', 'is_team_lead', 'is_active', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    public function organizationSites(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\OrganizationSite::class, 'organization_account_id', 'organization_account_id');
    }

    public function isZoneScopedAdmin(): bool
    {
        return ($this->role ?? null) === 'admin'
            && (
                ($this->access_scope ?? null) === 'zone'
                || ! empty($this->managed_service_zone_id)
            );
    }


    public function rendezVousClient(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\RendezVous::class, 'client_id');
    }

    public function rendezVousEmploye(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\RendezVous::class, 'employe_id');
    }


    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'id', 'id');
    }

    public function canChooseEmployee(): bool
    {
        return in_array($this->plan_type ?? 'standard', ['premium', 'business', 'enterprise'], true)
            || in_array($this->role ?? null, ['admin', 'entreprise'], true)
            || $this->isPlatformAdmin();
    }

    public function isReadOnlyAdmin(): bool
    {
        return ($this->role ?? null) === 'readonly_admin'
            || ($this->platform_role ?? null) === 'readonly_admin'
            || ($this->access_scope ?? null) === self::ACCESS_SCOPE_READONLY
            || ($this->access_scope ?? null) === 'readonly';
    }

    public function disponibilites(): HasMany
    {
        return $this->hasMany(Disponibilite::class);
    }

    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'organization_account_id');
    }

    public function activeCreditBalance(): float
    {
        if (! Schema::hasTable('client_credits')) {
            return 0.0;
        }

        return (float) DB::table('client_credits')
            ->where('user_id', $this->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->sum('remaining_amount');
    }

    public function hasOrganizationContext(): bool
    {
        return filled($this->organization_account_id)
            || filled($this->current_organization_id)
            || filled(data_get($this->metadata, 'organization_account_id'))
            || filled(data_get($this->metadata, 'entreprise_context'));
    }

    public function organizationContextId(): ?int
    {
        return $this->organization_account_id
            ?? $this->current_organization_id
            ?? data_get($this->metadata, 'organization_account_id')
            ?? data_get($this->metadata, 'entreprise_context.organization_account_id')
            ?? null;
    }

    public function isEntreprise(): bool
    {
        return in_array($this->role, [
            self::ROLE_ENTREPRISE,
            'entreprise',
            'client_company',
            'company_client',
        ], true);
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
                ->when(Schema::hasColumn('field_teams', 'is_active'), fn($q) => $q->where('is_active', true));
        }

        if (Schema::hasColumn('field_teams', 'team_lead_user_id')) {
            return $query->where('team_lead_user_id', $this->id)
                ->when(Schema::hasColumn('field_teams', 'is_active'), fn($q) => $q->where('is_active', true));
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
            })->when(Schema::hasColumn('field_teams', 'is_active'), fn($q) => $q->where('is_active', true));
        }

        return $query->whereRaw('1 = 0');
    }

    public function isFieldTeamLead(): bool
    {
        return $this->activeLedFieldTeams()->exists();
    }

    public function preferredByClients()
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
    // ─────────────────────────────────────────────────────
    // Compat helpers tests / legacy UI
    // ─────────────────────────────────────────────────────

    public const ACCESS_SCOPE_GLOBAL = 'global';
    public const ACCESS_SCOPE_ZONE = 'zone';
    public const ACCESS_SCOPE_READONLY = 'readonly';


    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isEmploye(): bool
    {
        return $this->role === 'employe';
    }

    public function isClient(): bool
    {
        return in_array($this->role, ['client', 'entreprise']);
    }
    public function getIsAdminAttribute(): bool
    {
        return $this->isAdmin();
    }

    public function getIsEmployeAttribute(): bool
    {
        return $this->isEmploye();
    }

    public function getIsClientAttribute(): bool
    {
        return $this->isClient();
    }

    public function getIsEntrepriseAttribute(): bool
    {
        return $this->isEntreprise();
    }

    public function canViewEmployeeAvailability(): bool
    {
        return $this->isPremium()
            || $this->isAdmin()
            || $this->isEmploye()
            || $this->isEntreprise();
    }

    public function primaryServiceZone()
    {
        return $this->belongsTo(\App\Models\ServiceZone::class, 'primary_service_zone_id');
    }
}
