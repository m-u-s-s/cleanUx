<?php

namespace App\Models;

use App\Models\Concerns\HasAdminCapabilities;
use App\Models\Concerns\HasBillingFeatures;
use App\Models\Concerns\HasOrganizationContext;
use App\Models\Concerns\HasProviderFeatures;
use App\Models\Concerns\HasUserTypeChecks;
use App\Services\I18n\LocaleResolver;
use App\Support\Notifications\NotificationChannelResolver;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Billable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;

/**
 * `profile_photo_url` vient du trait `HasProfilePhoto` de Jetstream, sous forme d'accesseur : l'attribut existe bien à l'exécution — les vues s'en servent déjà — mais n'étant ni une colonne ni une méthode, l'analyse statique ne le voit pas.
 *
 * @property ?Carbon $email_verified_at
 * @property ?Carbon $two_factor_confirmed_at
 * @property bool $is_active
 * @property ?array $metadata
 * @property ?array $permissions
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $unreadNotifications
 * @property-read string $profile_photo_url
 */
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    use Billable;
    use HasAdminCapabilities;
    use HasApiTokens;
    use HasBillingFeatures;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasOrganizationContext;
    use HasProfilePhoto;
    use HasProviderFeatures;
    use HasTeams;
    use HasUserTypeChecks;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /** Champs auto-assignables via Eloquent::fill/update/create. */
    protected $fillable = [
        'name',
        'email',
        'password',

        'account_type',

        'phone',
        'phone_verified_at',
        'tva_number',

        'locale',
        'timezone',
        'status',
        'is_active',

        'current_team_id',
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
        // MIROIR, PAS COMMANDE : `bootHasAdminCapabilities()` la reecrit depuis
        // `platform_role` a chaque enregistrement. La poser n'eleve personne.
        'is_super_admin',
        'admin_permissions',

        // Onboarding / provider
        'stripe_connect_status',
        'stripe_connect_account_id',
        // Écrits par StripeConnectService::syncAccountStatus(). Sans être assignables en masse,
        // ils étaient rejetés en silence par update() : la synchronisation semblait réussir et
        // ne persistait que le statut.
        'stripe_connect_onboarded_at',
        'stripe_connect_charges_enabled_at',
        'stripe_connect_payouts_enabled_at',

        // UI preferences
        'theme_preference',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        // LA PHRASE DU SIEGE N'EST NI ASSIGNABLE NI EXPOSEE : elle ne sort par aucune API,
        // aucun export, aucun journal.
        'seat_secret_hash',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'seat_claimed_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_confirmed_at' => 'datetime',
        'stripe_connect_onboarded_at' => 'datetime',
        'stripe_connect_charges_enabled_at' => 'datetime',
        'stripe_connect_payouts_enabled_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
        'permissions' => 'array',
    ];

    // ──────────────────────────────────────────────────────
    // Core relations
    // ──────────────────────────────────────────────────────

    /** @return HasOne<CustomerProfile, $this> */
    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    /** @return HasMany<Booking, $this> */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'client_user_id');
    }

    /** @return HasMany<AssistantConversation, $this> */
    public function assistantConversations(): HasMany
    {
        return $this->hasMany(AssistantConversation::class);
    }

    /** CE DESTINATAIRE VEUT-IL DE CE CANAL POUR CET ÉVÉNEMENT ? */
    public function wantsNotificationChannel(string $eventKey, string $channel): bool
    {
        return app(NotificationChannelResolver::class)->accepte($this, $eventKey, $channel);
    }

    /** Le numéro vers lequel router un SMS de notification. */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone ?: null;
    }

    /**
     * Les réservations de cette personne, comme cliente puis comme prestataire.
     *
     * @return HasMany<Booking, $this>
     */
    public function rendezVousClient(): HasMany
    {
        return $this->hasMany(Booking::class, 'client_id');
    }

    /** @return HasMany<Booking, $this> */
    public function rendezVousEmploye(): HasMany
    {
        return $this->hasMany(Booking::class, 'employe_id');
    }

    /** @return HasMany<Disponibilite, $this> */
    public function disponibilites(): HasMany
    {
        return $this->hasMany(Disponibilite::class);
    }

    /**
     * Clients who have marked this user as a favourite provider.
     *
     * LE ROLE D'ADMINISTRATION — un paquet de capacites, pas un tampon.
     *
     * @return BelongsTo<AdminRole, $this>
     */
    public function adminRole(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class, 'admin_role_id');
    }

    /**
     * @return BelongsToMany<self, $this>
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
     *
     * @return BelongsToMany<self, $this>
     */
    public function favoriteEmployees(): BelongsToMany
    {
        return $this->favoriteEmployes();
    }

    /**
     * Self-referencing relation (legacy usage — resolves to $this).
     *
     * @return BelongsTo<self, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(self::class, 'id', 'id');
    }

    // ──────────────────────────────────────────────────────
    // Role helpers — class-level overrides take precedence
    // over trait methods, preserving original semantics.
    // ──────────────────────────────────────────────────────

    /** Broad "is admin" check: uses platform_role primarily with legacy role fallback. */
    /** OTP téléphone — le numéro a-t-il été vérifié par code SMS. */
    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /** LE COMPTE EST-IL EN ÉTAT DE SERVIR — la définition unique de « suspendu ». */
    public function compteActif(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $statut = strtolower((string) ($this->status ?? 'active'));

        return ! in_array($statut, ['inactive', 'disabled', 'suspended', 'blocked'], true);
    }

    public function isAdmin(): bool
    {
        if (in_array($this->platform_role ?? null, ['admin', 'super_admin'], true)) {
            return true;
        }

        // Legacy fallback: role column still populated during transition
        return in_array($this->attributes['role'] ?? null, ['admin', 'super_admin'], true);
    }

    /** Client check: uses customer_type via profile or organization membership. */
    public function isClient(): bool
    {
        if ($this->isClientPersonal()) {
            return true;
        }

        return $this->isClientCompany();
    }

    /** Provider / employee role check: uses provider_type via provider profile. */
    public function isEmploye(): bool
    {
        return $this->isProviderIndependent() || $this->isProviderCompanyWorker();
    }

    /** Company-client role check: uses customer_type or organization membership. */
    public function isEntreprise(): bool
    {
        return $this->isClientCompany();
    }

    // ──────────────────────────────────────────────────────
    // HasLocalePreference implementation
    // ──────────────────────────────────────────────────────

    /** Laravel uses this method to resolve the locale when sending notifications/mails, ensuring each recipient receives content in their own language — not the admin session locale. */
    public function preferredLocale(): ?string
    {
        $raw = $this->locale ?? null;
        if (! $raw) {
            return null;
        }

        return app(LocaleResolver::class)->normalize($raw);
    }
}
