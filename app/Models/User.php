<?php

namespace App\Models;

use App\Models\Concerns\HasAdminCapabilities;
use App\Models\Concerns\HasBillingFeatures;
use App\Models\Concerns\HasLegacyRoleCompatibility;
use App\Models\Concerns\HasOrganizationContext;
use App\Models\Concerns\HasProviderFeatures;
use App\Models\Concerns\HasUserTypeChecks;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail, HasLocalePreference
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use Billable;
    use HasLegacyRoleCompatibility;
    use HasAdminCapabilities;
    use HasUserTypeChecks;
    use HasOrganizationContext;
    use HasProviderFeatures;
    use HasBillingFeatures;

    /**
     * Champs auto-assignables via Eloquent::fill/update/create.
     *
     * SECURITY : Tout controller modifiant User DOIT utiliser `$request->validated()`
     * et JAMAIS `$request->all()`. Si un attribut peut être self-elevé (ex: role),
     * le retirer du payload validé via FormRequest.
     */
    protected $fillable = [
        'name',
        'email',
        'password',

        'tenant_id',

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
    // Core relations
    // ──────────────────────────────────────────────────────

    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'client_user_id');
    }

    public function assistantConversations(): HasMany
    {
        return $this->hasMany(AssistantConversation::class);
    }

    public function rendezVousClient(): HasMany
    {
        return $this->hasMany(\App\Models\RendezVous::class, 'client_id');
    }

    public function rendezVousEmploye(): HasMany
    {
        return $this->hasMany(\App\Models\RendezVous::class, 'employe_id');
    }

    public function disponibilites(): HasMany
    {
        return $this->hasMany(Disponibilite::class);
    }

    /**
     * Clients who have marked this user as a favourite provider.
     */
    public function favoriteEmployes(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'provider_favorites',
            'customer_user_id',
            'provider_user_id'
        )->withPivot(['is_favorite', 'status'])->withTimestamps();
    }

    /**
     * Alias kept for backwards compatibility.
     */
    public function favoriteEmployees(): BelongsToMany
    {
        return $this->favoriteEmployes();
    }

    /**
     * Self-referencing relation (legacy usage — resolves to $this).
     */
    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'id', 'id');
    }

    // ──────────────────────────────────────────────────────
    // Role helpers — class-level overrides take precedence
    // over trait methods, preserving original semantics.
    // ──────────────────────────────────────────────────────

    /**
     * Broad "is admin" check: matches both role and platform_role.
     * Overrides HasLegacyRoleCompatibility so the simple role check
     * stays the canonical source without DB round-trips.
     */
    public function isAdmin(): bool
    {
        return in_array($this->platform_role ?? null, ['admin', 'super_admin'], true)
            || ($this->role ?? null) === 'admin';
    }

    /**
     * Includes 'entreprise' as a client-side role (policy requirement).
     * Overrides HasLegacyRoleCompatibility to preserve original behaviour.
     */
    public function isClient(): bool
    {
        return in_array($this->role, ['client', 'entreprise'], true);
    }

    /**
     * Provider / employee role check.
     * Overrides HasLegacyRoleCompatibility to preserve original behaviour.
     */
    public function isEmploye(): bool
    {
        return ($this->role ?? null) === 'employe';
    }

    /**
     * Company-client role check.
     * Overrides HasLegacyRoleCompatibility to preserve original behaviour.
     */
    public function isEntreprise(): bool
    {
        return in_array($this->role, [
            self::ROLE_ENTREPRISE,
            'entreprise',
            'client_company',
            'company_client',
        ], true);
    }

    // ──────────────────────────────────────────────────────
    // HasLocalePreference implementation
    // ──────────────────────────────────────────────────────

    /**
     * Laravel uses this method to resolve the locale when sending
     * notifications/mails, ensuring each recipient receives content
     * in their own language — not the admin session locale.
     */
    public function preferredLocale(): ?string
    {
        $raw = $this->locale ?? null;
        if (! $raw) {
            return null;
        }

        return app(\App\Services\I18n\LocaleResolver::class)->normalize($raw);
    }
}
