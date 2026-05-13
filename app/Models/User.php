<?php

namespace App\Models;

use App\Enums\AssistantContextRole;
use App\Enums\CustomerType;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Services\PermissionService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
    public const ACCESS_SCOPE_ZONE = 'zone';
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
        return $this->attributes['current_organization_id'] ?? null;
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
        return $this->customerProfile?->customer_type === CustomerType::COMPANY->value;
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

    public function canAccessAdminModule(): bool
    {
        $isAdmin = in_array($this->role, ['admin', 'super_admin'], true)
            || in_array($this->platform_role, ['admin', 'super_admin'], true);

        if (! $isAdmin) {
            return false;
        }

        if (isset($this->is_active) && ! $this->is_active) {
            return false;
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

        $acceptedPermissions = [
            'manage-modules',
            'manage_modules',
            'admin.modules',
            'modules.manage',
            'platform.modules.manage',
            'platform_modules.manage',
        ];

        foreach ($acceptedPermissions as $permission) {
            if (array_key_exists($permission, $permissions) && (bool) $permissions[$permission]) {
                return true;
            }

            if (in_array($permission, $permissions, true)) {
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
            || ($this->platform_role ?? null) === 'readonly_admin';
    }

    public function disponibilites(): HasMany
    {
        return $this->hasMany(Disponibilite::class);
    }

    public function organizationAccount(): BelongsTo
    {
        return $this->belongsTo(OrganizationAccount::class, 'current_organization_id');
    }
}
