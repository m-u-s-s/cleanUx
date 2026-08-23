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
 * @property ?Carbon $email_verified_at
 * @property ?Carbon $two_factor_confirmed_at
 * @property bool $is_active
 * @property array $metadata
 * @property array $permissions
 * @property-read DatabaseNotificationCollection<int, DatabaseNotification> $unreadNotifications
 *
 * `profile_photo_url` vient du trait `HasProfilePhoto` de Jetstream, sous forme d'accesseur :
 * l'attribut existe bien à l'exécution — les vues s'en servent déjà — mais n'étant ni une colonne
 * ni une méthode, l'analyse statique ne le voit pas.
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

    /**
     * Champs auto-assignables via Eloquent::fill/update/create.
     *
     * SECURITY : Tout controller modifiant User DOIT utiliser `$request->validated()`
     * et JAMAIS `$request->all()`. Si un attribut peut être self-elevé (ex: role),
     * le retirer du payload validé via FormRequest.
     *
     * QUATRE COLONNES SONT VOLONTAIREMENT ABSENTES DE CETTE LISTE — elles décident QUI TU ES,
     * pas ce que tu préfères :
     *
     *   - `platform_role`      : `admin` / `super_admin` ouvrent toute la console d'administration
     *                            (`HasAdminCapabilities::canAccessAdminModule()`).
     *   - `role`               : colonne dépréciée, mais encore lue par `CheckRole`, par les
     *                            gardes `role:employe` et par des callbacks de diffusion.
     *   - `organization_account_id`
     *   - `current_organization_id` : ces deux-là DÉSIGNENT L'ORGANISATION dont on lit les données.
     *                            Se les assigner soi-même, c'est entrer chez le voisin.
     *
     * Le parcours d'inscription passe `$request->all()` à `CreateNewUser` (c'est Fortify qui le
     * fait, pas nous) : tant que ces clés étaient assignables en masse, un simple champ caché
     * `platform_role=admin` dans le formulaire d'inscription suffisait. Elles s'écrivent
     * désormais par `forceFill()` uniquement — un appel explicite, visible en revue, jamais
     * pilotable depuis le navigateur.
     *
     * `Model::preventSilentlyDiscardingAttributes` étant actif hors production, toute tentative
     * de les repasser en masse LÈVE une `MassAssignmentException` au lieu de les ignorer : la
     * régression se voit tout de suite au lieu de s'écrire en silence.
     */
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
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
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

    /** @return HasOne<Booking, $this> */
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

    /**
     * CE DESTINATAIRE VEUT-IL DE CE CANAL POUR CET ÉVÉNEMENT ?
     *
     * `InteractsWithUserNotificationPreferences::preferredChannels()` cherchait cette méthode
     * depuis toujours, avec un garde `method_exists()`. Elle n'existait sur AUCUN modèle : le garde
     * échouait en silence, la fonction rendait ses valeurs par défaut, et la matrice de préférences
     * — versionnée, auditée, avec son écran de réglage — n'était consultée par aucun envoi.
     * L'utilisateur qui coupait les rappels continuait de les recevoir, et rien nulle part ne
     * signalait que son choix était ignoré.
     *
     * La traduction entre le vocabulaire de Laravel et celui de la matrice vit dans
     * `NotificationChannelResolver` : un seul endroit, pour qu'elle ne diverge pas.
     */
    public function wantsNotificationChannel(string $eventKey, string $channel): bool
    {
        return app(NotificationChannelResolver::class)->accepte($this, $eventKey, $channel);
    }

    /**
     * Le numéro vers lequel router un SMS de notification. Le canal `sms` le demande avant de
     * retomber sur l'attribut `phone`.
     */
    public function routeNotificationForSms(): ?string
    {
        return $this->phone ?: null;
    }

    /**
     * Les réservations de cette personne, comme cliente puis comme prestataire.
     *
     * ELLES LISAIENT LE MIROIR, PAS LA SOURCE. `RendezVous` désigne la table `rendez_vous`, une
     * copie de `bookings` alimentée au fil de l'eau par un trait — au mieux, puisque son écriture
     * est enveloppée dans un `catch` muet. Un échec de recopie ne laissait aucune trace et la
     * plateforme continuait de décider dessus : `SmartDispatchService` y comptait les missions du
     * jour d'un prestataire pour l'affecter, et `GestionUtilisateurs` y filtrait par zone.
     *
     * Décider sur une copie dont on ignore si elle est à jour, c'est décider au hasard une fois de
     * temps en temps — le pire des régimes, parce qu'il est invisible.
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

    /** @return HasMany<self, $this> */
    public function disponibilites(): HasMany
    {
        return $this->hasMany(Disponibilite::class);
    }

    /**
     * Clients who have marked this user as a favourite provider.
     *
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

    /**
     * Broad "is admin" check: uses platform_role primarily with legacy role fallback.
     */
    /** OTP téléphone — le numéro a-t-il été vérifié par code SMS. */
    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /**
     * LE COMPTE EST-IL EN ÉTAT DE SERVIR — la définition unique de « suspendu ».
     *
     * Elle vivait dans `EnsureActiveAccount`, un middleware posé sur tous les groupes web et sur
     * AUCUNE route d'API : un compte suspendu obtenait un jeton neuf et gardait l'application
     * mobile entière — sa boîte d'offres, ses réservations, son portefeuille. Bannir quelqu'un ne
     * l'arrêtait que dans le navigateur.
     *
     * Trois lecteurs, une seule règle : le middleware web, l'authentification par jeton Sanctum
     * (`AppServiceProvider`) et la porte de connexion mobile (`ApiAuthController::login`). Deux
     * copies de cette condition finiraient par diverger, et c'est la moitié oubliée qui décide.
     *
     * `status` est comparé en minuscules : la colonne est libre et des seeders y ont écrit des
     * majuscules.
     */
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

    /**
     * Client check: uses customer_type via profile or organization membership.
     * Includes both personal and company clients.
     */
    public function isClient(): bool
    {
        if ($this->isClientPersonal()) {
            return true;
        }

        return $this->isClientCompany();
    }

    /**
     * Provider / employee role check: uses provider_type via provider profile.
     */
    public function isEmploye(): bool
    {
        return $this->isProviderIndependent() || $this->isProviderCompanyWorker();
    }

    /**
     * Company-client role check: uses customer_type or organization membership.
     */
    public function isEntreprise(): bool
    {
        return $this->isClientCompany();
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

        return app(LocaleResolver::class)->normalize($raw);
    }
}
