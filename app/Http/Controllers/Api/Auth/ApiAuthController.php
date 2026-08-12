<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\CustomerType;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Auth\LoginRequest;
use App\Http\Requests\Api\Auth\RegisterRequest;
use App\Jobs\Kyb\VerifyBusinessEntity;
use App\Models\CustomerProfile;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Catalog\ProviderCoverageWriter;
use App\Services\KybV2\BusinessOnboardingService;
use App\Services\OnboardingV2\OnboardingEngine;
use App\Services\Promotion\ReferralService;
use App\Services\Sms\PhoneVerificationService;
use App\Support\Mobile\AppAudience;
use App\Support\Organizations\ContratDeRoleMobile;
use App\Support\Validation\BusinessNumber;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Phase 12 — Authentification API mobile (Sanctum tokens).
 *
 * Endpoints :
 *   POST /api/auth/login    → token + user
 *   POST /api/auth/register → token + user (clients seulement)
 *   POST /api/auth/logout   → révoque le token courant
 *
 * Sécurité :
 *   - Rate limit 5 tentatives/min par IP+email sur login
 *   - Email vérification optionnelle (selon ta config Fortify)
 *   - Le token retourné est un PersonalAccessToken Sanctum, durée illimitée
 *     par défaut (configurable via config/sanctum.php)
 */
/**
 * @group Authentication
 */
class ApiAuthController extends Controller
{
    /**
     * Authenticate a user and return a Sanctum token.
     *
     * @bodyParam email string required The user's email address. Example: alice@example.com
     * @bodyParam password string required The user's password (min 6 chars). Example: secret123
     * @bodyParam device_name string Optional device identifier stored with the token. Example: iPhone 15
     *
     * @response 200 {"ok": true, "token": "1|abc123def456...", "user": {"id": 1, "name": "Alice Dupont", "email": "alice@example.com", "phone": "+32471000001", "role": "client", "platform_role": "user", "locale": "fr", "is_provider": false, "is_admin": false, "organization_account_id": null}}
     * @response 422 {"message": "Identifiants incorrects.", "errors": {"email": ["Identifiants incorrects."]}}
     * @response 429 {"message": "Trop de tentatives. Réessaie dans 42 secondes.", "errors": {"email": ["Trop de tentatives. Réessaie dans 42 secondes."]}}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Rate limit : 5 tentatives par minute par email+IP
        $key = 'api-login:'.strtolower($data['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Trop de tentatives. Réessaie dans {$seconds} secondes.",
            ]);
        }

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects.',
            ]);
        }

        /*
         * Chaque application n'accepte que le public qu'elle sert.
         *
         * Le contrôle vient APRÈS la vérification du mot de passe et AVANT l'émission du jeton :
         * après, pour ne pas révéler à un inconnu qu'une adresse existe et de quel type elle est ;
         * avant, parce qu'émettre un jeton puis refuser l'écran laisserait une session valide dans
         * une application qui n'en veut pas.
         *
         * La limite de tentatives n'est PAS remise à zéro sur ce chemin : le mot de passe était
         * bon, mais rien ne doit permettre de sonder les comptes application par application sans
         * compteur.
         */
        $app = AppAudience::declared($request);

        if (! AppAudience::allows($user, $app)) {
            return response()->json(AppAudience::refusal($user, (string) $app) + ['ok' => false], 403);
        }

        // Reset rate limit après login réussi
        RateLimiter::clear($key);

        $deviceName = $data['device_name'] ?? $request->userAgent() ?? 'mobile';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'ok' => true,
            'token' => $token,
            'user' => $this->serializeUser($user),
        ]);
    }

    /**
     * Register a new client account and return a Sanctum token.
     *
     * @bodyParam name string required Full display name. Example: Alice Dupont
     * @bodyParam email string required Unique email address. Example: alice@example.com
     * @bodyParam password string required Minimum 8 characters. Example: s3cur3pass!
     * @bodyParam password_confirmation string required Must match password. Example: s3cur3pass!
     * @bodyParam phone string Optional phone number. Example: +32471000001
     * @bodyParam locale string Optional UI locale (fr, nl, en). Example: fr
     * @bodyParam accept_terms boolean required Must be accepted (1/true). Example: 1
     * @bodyParam device_name string Optional device identifier. Example: Android Pixel 8
     * @bodyParam referral_code string Optional referral code from an existing user. Example: REF-ABCD1234
     *
     * @response 201 {"ok": true, "token": "2|xyz789...", "user": {"id": 42, "name": "Alice Dupont", "email": "alice@example.com", "phone": "+32471000001", "role": "client", "platform_role": "user", "locale": "fr", "is_provider": false, "is_admin": false, "organization_account_id": null}}
     * @response 422 {"message": "The email has already been taken.", "errors": {"email": ["The email has already been taken."]}}
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $asProvider = ($data['account_type'] ?? 'client') === 'provider';

        // Le téléphone est vérifié par SMS au premier écran du parcours prestataire, donc avant
        // que ce compte existe. Le jeton rendu alors est échangé ici, une seule fois, contre le
        // numéro qu'il porte : c'est la seule façon d'arriver ici avec `phone_verified_at` posé.
        // Un jeton absent, expiré, déjà échangé ou portant un autre numéro laisse simplement
        // l'inscription se poursuivre avec un téléphone non vérifié, sans la faire échouer.
        $verifiedPhone = null;
        if (! empty($data['phone_verification_token'])) {
            $verifiedPhone = app(PhoneVerificationService::class)->consumeRegistrationToken(
                $data['phone_verification_token'],
                $data['phone'] ?? null,
            );
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $verifiedPhone ?? $data['phone'] ?? null,
            'locale' => $data['locale'] ?? 'fr',
        ]);

        // `role` et `platform_role` ne sont plus assignables en masse : ce sont les colonnes qui
        // décident de ce que le compte peut atteindre. Elles se posent ici par `forceFill`, à
        // partir du seul `account_type` validé, jamais d'une clé libre du corps de la requête.
        $user->forceFill([
            'platform_role' => User::PLATFORM_USER,
            'role' => $asProvider ? 'employe' : 'client',
        ])->save();

        if ($verifiedPhone !== null && Schema::hasColumn('users', 'phone_verified_at')) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        // L'app prestataire crée un compte capable de compléter son dossier, et rien d'autre :
        // toute la surface prestataire est gardée par `role:employe`, y compris les routes
        // d'onboarding. Sans rôle `employe`, le compte n'avait aucun moyen de devenir
        // prestataire — il se connectait et recevait 403 partout, définitivement.
        // self_registered_at est posé par forceFill car il n'est pas assignable en masse :
        // c'est lui qui porte la restriction, le rendre fillable permettrait de la lever.
        if ($asProvider) {
            $this->createProviderIdentity($user, $data);
        } else {
            $this->createClientIdentity($user, $data);
        }

        // `User implements MustVerifyEmail` et EventServiceProvider écoute déjà Registered avec
        // SendEmailVerificationNotification — mais l'événement n'était JAMAIS émis ici : aucun
        // email de vérification n'est jamais parti d'une inscription mobile.
        //
        // Soft-fail comme les autres effets de bord de cette méthode : une panne SMTP ne doit pas
        // faire échouer la création du compte, l'utilisateur se retrouverait sans compte ET sans
        // explication alors que son inscription est valide.
        try {
            event(new Registered($user));
        } catch (\Throwable $e) {
            Log::warning("Envoi de l'email de vérification impossible", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Apply referral code if provided — soft-fail, never blocks registration
        if (! empty($data['referral_code']) && config('referral.enabled', true)) {
            try {
                $sourceChannel = $request->header('X-Source-Channel', 'api_register');
                app(ReferralService::class)->registerReferral(
                    $data['referral_code'],
                    $user,
                    $sourceChannel,
                    $request->ip(),
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $deviceName = $data['device_name'] ?? $request->userAgent() ?? 'mobile';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'ok' => true,
            'token' => $token,
            'user' => $this->serializeUser($user),
        ], 201);
    }

    /**
     * Crée l'identité cliente du compte : particulier ou société.
     *
     * L'inscription mobile ne créait AUCUN CustomerProfile, là où la voie web en crée un
     * systématiquement. Or ce profil est lu par le moteur de sélection de prestataire, la prise
     * de rendez-vous et l'identité renvoyée par /auth/me : un client inscrit depuis l'application
     * en était donc dépourvu, sans que rien ne le signale.
     *
     * Une société cliente donne lieu à un OrganizationAccount `client_company` dont le signataire
     * est `owner` — c'est ce que consomment le multi-sites, les contrats B2B et la facturation
     * centralisée. Contrairement à une société prestataire, elle est active d'emblée : rien n'est
     * à vérifier avant qu'elle puisse commander un service, et l'y contraindre bloquerait une
     * cliente légitime.
     *
     * @param  array<string, mixed>  $data
     */
    private function createClientIdentity(User $user, array $data): void
    {
        $asCompany = ($data['client_kind'] ?? 'individual') === 'company';

        try {
            if ($asCompany) {
                $organization = OrganizationAccount::create([
                    'name' => $data['company_name'],
                    'legal_name' => $data['company_name'],
                    'slug' => $this->uniqueOrganizationSlug($data['company_name']),
                    'type' => OrganizationType::CLIENT_COMPANY->value,
                    'status' => 'active',
                    'tva_number' => $data['vat_number'] ?? null,
                    'email' => $data['email'],
                ]);

                OrganizationMember::create([
                    'organization_account_id' => $organization->id,
                    'user_id' => $user->id,
                    'role' => OrganizationRole::OWNER->value,
                    'status' => 'active',
                    'joined_at' => now(),
                ]);

                $user->forceFill([
                    'organization_account_id' => $organization->id,
                    'current_organization_id' => $organization->id,
                ])->save();
            }

            CustomerProfile::create([
                'user_id' => $user->id,
                'customer_type' => $asCompany ? CustomerType::COMPANY->value : CustomerType::PERSONAL->value,
                'plan_type' => 'standard',
                'plan_status' => 'inactive',
            ]);
        } catch (\Throwable $e) {
            // Soft-fail comme les autres effets de bord de l'inscription : un compte créé sans son
            // profil reste utilisable et rattrapable, un compte non créé ne l'est pas.
            Log::warning("Création de l'identité cliente impossible", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Crée l'identité prestataire du compte : profil seul pour un indépendant, profil rattaché à
     * une organisation `provider_company` pour une société.
     *
     * Une société prestataire est modélisée par un OrganizationAccount dont le signataire est
     * membre `owner` — c'est ce que consomment l'espace web provider-company (dispatch, équipe)
     * et le rattachement des missions via `provider_organization_id`. Créer seulement un profil
     * marqué « société » produirait un compte incapable d'avoir la moindre équipe.
     *
     * @param  array<string, mixed>  $data
     */
    private function createProviderIdentity(User $user, array $data): void
    {
        $asCompany = ($data['provider_kind'] ?? 'independent') === 'company';

        $organizationId = null;

        if ($asCompany) {
            $organization = OrganizationAccount::create([
                'name' => $data['company_name'],
                'legal_name' => $data['company_name'],
                'slug' => $this->uniqueOrganizationSlug($data['company_name']),
                'type' => 'provider_company',
                'status' => 'pending',
                'tva_number' => $data['vat_number'] ?? null,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);

            OrganizationMember::create([
                'organization_account_id' => $organization->id,
                'user_id' => $user->id,
                'role' => OrganizationRole::OWNER->value,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $organizationId = $organization->id;

            $this->openBusinessVerification($user, $data);
        }

        $profile = ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $organizationId,
            /*
             * `COMPANY_WORKER`, ET SURTOUT PAS `COMPANY`.
             *
             * Le fondateur d'une société prestataire EST un membre de cette société : c'est ce que
             * `isProviderCompanyWorker()` teste, et c'est ce que pose l'inscription web
             * (`CreateNewUser::createProviderCompany`). Cette ligne posait `COMPANY`, qu'aucune
             * lecture ne reconnaît.
             *
             * Conséquence mesurée : un patron inscrit depuis le mobile ne résolvait NI en société
             * NI en prestataire — `isEmploye()` étant faux pour lui — et retombait sur le repli
             * `client_individuelle`. Il atterrissait dans l'espace client, sans ses missions, sans
             * sa société, avec une organisation pourtant créée et un rôle `owner` en base.
             *
             * Deux parcours écrivaient la même identité par des chemins différents, et une seule
             * des deux écritures était reconnue en lecture.
             */
            'provider_type' => $asCompany ? ProviderType::COMPANY_WORKER : ProviderType::INDEPENDENT,
            'status' => 'pending',
            'verification_status' => 'unverified',
        ]);

        // forceFill : self_registered_at n'est pas assignable en masse, c'est elle qui porte la
        // restriction d'accès tant que le compte n'a pas été approuvé.
        $profile->forceFill(['self_registered_at' => now()])->save();

        $this->attachDeclaredTrade($user, $data);
        $this->attachDeclaredCoverage($user, $data);
        $this->openVerificationJourney($user);
    }

    /**
     * La couverture déclarée : métiers ET zones.
     *
     * `trade_id` seul ne dit QUE ce que le prestataire fait, jamais où — et le dispatch devait
     * alors deviner son périmètre à partir de sa position du jour. `ProviderCoverageWriter` écrit
     * les deux tables que lit la requête candidate, et valide chaque identifiant contre le
     * catalogue : ce qui arrive vient de l'application, donc n'est cru sur parole nulle part.
     *
     * @param  array<string, mixed>  $data
     */
    private function attachDeclaredCoverage(User $user, array $data): void
    {
        $metiers = array_values(array_filter(array_map('intval', (array) ($data['trade_ids'] ?? []))));
        $zones = array_values(array_filter(array_map('intval', (array) ($data['zone_ids'] ?? []))));

        // Le métier principal déclaré à l'étape précédente reste dans la liste : les deux champs
        // coexistent le temps que toutes les versions installées envoient `trade_ids`.
        if (! empty($data['trade_id'])) {
            $metiers[] = (int) $data['trade_id'];
        }

        if ($metiers === [] && $zones === []) {
            return;
        }

        app(ProviderCoverageWriter::class)->sync(
            $user,
            array_values(array_unique($metiers)),
            $zones,
            ! empty($data['trade_id']) ? (int) $data['trade_id'] : null,
        );
    }

    /**
     * Rattache le métier déclaré à l'inscription, avec les réponses aux questions propres à ce
     * métier (trades.provider_form_schema).
     *
     * Sans métier, le matching n'a rien sur quoi travailler et le prestataire ne recevrait aucune
     * mission — c'est aussi ce que contrôle l'étape « déclarer vos métiers » du parcours.
     *
     * @param  array<string, mixed>  $data
     */
    private function attachDeclaredTrade(User $user, array $data): void
    {
        $tradeId = $data['trade_id'] ?? null;

        if (! $tradeId) {
            return;
        }

        $answers = $data['trade_answers'] ?? [];

        $trade = Trade::find($tradeId);

        // SkillDeclareValidator ne lit PAS `trade_user` : il cherche provider_trades /
        // provider_skills (absentes de ce schéma) ou provider_profiles.metadata.trade_codes. On y
        // recopie donc le métier déclaré, sans quoi l'étape « déclarer vos métiers » resterait en
        // échec alors que le prestataire l'a renseigné dès l'inscription.
        if ($trade) {
            ProviderProfile::query()
                ->where('user_id', $user->id)
                ->update(['metadata' => json_encode(['trade_codes' => [$trade->code ?: $trade->slug]])]);
        }

        DB::table('trade_user')->insert([
            'user_id' => $user->id,
            'trade_id' => (int) $tradeId,
            'is_primary' => true,
            // `notes` est le seul champ libre du pivot : on y conserve les réponses telles quelles
            // pour la revue admin, plutôt que de les perdre faute de colonne dédiée.
            'notes' => $answers ? json_encode($answers, JSON_UNESCAPED_UNICODE) : null,
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Ouvre le parcours de vérification obligatoire (profil, contrat, identité, documents,
     * métiers) dès la création du compte.
     *
     * Soft-fail délibéré : un module désactivé ou un parcours absent ne doit pas faire échouer la
     * création du compte. L'utilisateur se retrouverait sans compte ET sans explication, alors que
     * le middleware provider.approved le bloque de toute façon tant qu'il n'est pas approuvé.
     */
    private function openVerificationJourney(User $user): void
    {
        try {
            app(OnboardingEngine::class)->startFor($user);
        } catch (\Throwable $e) {
            Log::warning('Ouverture du parcours de vérification impossible', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * `organization_accounts.slug` porte un index unique : deux sociétés homonymes qui
     * s'inscrivent ne doivent pas faire échouer la seconde inscription.
     */
    /**
     * Ouvre la vérification d'entreprise d'une société qui s'inscrit.
     *
     * Le module de vérification d'entreprise était entièrement construit — immatriculation contre
     * les registres officiels, TVA via VIES, criblage des sanctions, score de risque pondéré —
     * mais AUCUNE entité n'était jamais créée : il n'avait donc jamais rien à vérifier. Une
     * société s'inscrivait, son organisation était créée, et sa légitimité n'était contrôlée par
     * personne.
     *
     * Soft-fail intégral : une inscription valide ne doit pas échouer parce qu'un registre est
     * injoignable ou qu'un numéro sort des schémas connus. Sans entité, la société suit
     * simplement la voie manuelle, comme avant.
     *
     * @param  array<string, mixed>  $data
     */
    private function openBusinessVerification(User $user, array $data): void
    {
        $number = $data['vat_number'] ?? null;

        if (! $number) {
            return;
        }

        try {
            $country = BusinessNumber::countryFor($number);
            $identifierType = BusinessNumber::identifierType($number);

            // Chaque pays a son vocabulaire d'identifiant et le module refuse un type qu'il ne
            // déclare pas : mieux vaut ne rien créer que de créer une entité invérifiable.
            if (! $country || ! $identifierType) {
                return;
            }

            $entity = app(BusinessOnboardingService::class)->startVerification([
                'legal_name' => $data['company_name'],
                'country_code' => $country,
                'identifier_type' => $identifierType,
                'identifier_value' => BusinessNumber::bareNumber($number),
                'vat_id' => BusinessNumber::normalise($number),
                'contact_email' => $data['email'],
                'contact_user_id' => $user->id,
            ], $user);

            // Les appels sortants sont mis en file : les exécuter ici ralentirait l'inscription
            // d'autant, et l'indisponibilité d'un registre la ferait échouer.
            VerifyBusinessEntity::dispatch($entity);
        } catch (\Throwable $e) {
            Log::warning("Ouverture de la vérification d'entreprise impossible", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function uniqueOrganizationSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'prestataire';
        $slug = $base;
        $suffix = 1;

        while (OrganizationAccount::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    /**
     * Revoke the current access token (logout from this device only).
     *
     * @response 200 {"ok": true}
     */
    public function logout(Request $request): JsonResponse
    {
        // Révoque le token courant uniquement (pas tous les devices)
        $token = $request->user()->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Revoke all access tokens for the authenticated user (logout from all devices).
     *
     * @response 200 {"ok": true, "revoked_all": true}
     */
    public function logoutAll(Request $request): JsonResponse
    {
        // Révoque TOUS les tokens (utile pour "déconnecte tous mes appareils")
        $request->user()->tokens()->delete();

        return response()->json(['ok' => true, 'revoked_all' => true]);
    }

    /**
     * Serializer minimal utilisé par login + register pour réponse cohérente.
     */
    protected function serializeUser(User $user): array
    {
        return array_merge([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone ?? null,
            'platform_role' => $user->platform_role ?? null,
            /*
             * Le rôle canonique, identique à celui que `/auth/me` annonce à la reprise de session.
             * La divergence entre ces deux réponses est ce qui a produit, un par un, les drapeaux
             * ci-dessous — chacun ajouté après qu'un compte eut perdu sa casquette au redémarrage.
             */
            'role' => $user->roleCanonique()->value,
            'is_super_admin' => $user->roleCanonique() === Role::SUPER_ADMIN,
            'locale' => $user->locale ?? 'fr',
            'is_provider' => method_exists($user, 'isProvider') && $user->isProvider(),
            'is_admin' => method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin(),
            'is_client' => method_exists($user, 'isClient') && $user->isClient(),
            'is_entreprise' => method_exists($user, 'isEntreprise') && $user->isEntreprise(),
        ],
            /*
             * LA PARITÉ N'EST PLUS UNE INTENTION, C'EST LE MÊME CODE.
             *
             * Cette méthode calculait sa propre version du contexte d'organisation : un
             * `organization_account_id` résolu en deux niveaux là où `/auth/me` en a quatre, et NI
             * `organization_type` NI `can_manage_company`. Un dirigeant de société prestataire se
             * connectait donc sans que rien n'indique à l'application quel espace ouvrir — puis
             * l'obtenait au redémarrage suivant, ce qui rendait le défaut intermittent et sa cause
             * invisible.
             *
             * `organization_account_id` change de source au passage : `organizationContextId()`
             * plutôt que `organization_account_id ?? current_organization_id`. C'est un ÉLARGISSEMENT
             * — les deux colonnes lues auparavant restent les deux premiers replis de la méthode.
             */
            app(ContratDeRoleMobile::class)->pour($user)
        );
    }
}
