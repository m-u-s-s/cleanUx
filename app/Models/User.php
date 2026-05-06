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
        'phone',
        'locale',
        'timezone',
        'platform_role',
        'status',
        'is_active',
        'current_team_id',
        'current_organization_id',
        'profile_photo_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at'          => 'datetime',
        'two_factor_confirmed_at'    => 'datetime',
        'is_active'                  => 'boolean',
        'password'                   => 'hashed',
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
}
