<?php

namespace App\Actions\Fortify;

use App\Enums\CustomerType;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\CustomerProfile;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Rules\NumeroDEntrepriseNonRevendique;
use App\Rules\ValidBusinessNumber;
use App\Services\Availability\DefaultAvailabilityProvisioner;
use App\Services\Catalog\ProviderCoverageWriter;
use App\Services\Promotion\ReferralService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],

            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],

            'password' => $this->passwordRules(),

            'account_type' => ['nullable', 'string'],

            // Champs entreprise cliente
            'company_name' => [
                'required_if:account_type,client_company',
                'nullable',
                'string',
                'max:255',
            ],

            // LA MÊME RÈGLE QUE SUR L'INSCRIPTION MOBILE, ET C'EST TOUT LE POINT.
            'tva_number' => [
                'nullable',
                'string',
                'max:50',
                new ValidBusinessNumber,
                new NumeroDEntrepriseNonRevendique(
                    ($input['account_type'] ?? null) === 'provider_company'
                        ? OrganizationType::PROVIDER_COMPANY->value
                        : OrganizationType::CLIENT_COMPANY->value
                ),
            ],

            // Champs prestataire société
            'provider_company_name' => [
                'required_if:account_type,provider_company',
                'nullable',
                'string',
                'max:255',
            ],

            // MÉTIERS ET ZONES — les deux tables que lit la requête candidate du dispatch.
            'trade_ids' => ['nullable', 'array'],
            'trade_ids.*' => ['integer'],
            'zone_ids' => ['nullable', 'array'],
            'zone_ids.*' => ['integer'],

            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature()
                ? ['accepted', 'required']
                : ['nullable'],
        ])->validate();

        return DB::transaction(function () use ($input) {
            // Important : Le test Jetstream standard n’envoie pas account_type.
            $accountType = $input['account_type'] ?? 'client_personal';

            // ── Créer l'utilisateur de base ──
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],

                // Important : Si User.php contient le cast 'password' => 'hashed', il ne faut PAS faire Hash::make ici.
                'password' => $input['password'],

                'account_type' => $accountType,

                'locale' => app()->getLocale() === 'nl' ? 'nl_BE' : 'fr_BE',
                'timezone' => 'Europe/Brussels',

                'status' => 'active',
                'is_active' => true,
            ]);

            // LE RÔLE SE DÉDUIT, IL NE SE DEMANDE PAS.
            $user->forceFill([
                'role' => in_array($accountType, ['provider_independent', 'provider_company'], true)
                    ? 'employe'
                    : 'client',
                'platform_role' => User::PLATFORM_USER,
            ])->save();

            // ── Créer le profil selon le type ──
            match ($accountType) {
                'client', 'client_personal' => $this->createClientPersonal($user),

                'client_company' => $this->createClientCompany($user, $input),

                'provider_independent' => $this->createProviderIndependent($user, $input),

                'provider_company' => $this->createProviderCompany($user, $input),

                default => $this->createClientPersonal($user),
            };

            $this->attachReferral($user, $input);

            return $user;
        });
    }

    private function attachReferral(User $user, array $input): void
    {
        $service = app(ReferralService::class);

        try {
            $service->ensureReferralCode($user);
        } catch (\Throwable $e) {
            report($e);
        }

        $rawCode = $input['referral_code'] ?? $input['ref'] ?? null;
        if (! $rawCode) {
            return;
        }

        try {
            $service->registerReferral(
                referralCode: (string) $rawCode,
                referee: $user,
                sourceChannel: $input['referral_channel'] ?? 'signup',
                ip: request()?->ip(),
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    // ──────────────────────────────────────────────────────
    // 1. Client particulier
    // ──────────────────────────────────────────────────────
    private function createClientPersonal(User $user): void
    {
        CustomerProfile::create([
            'user_id' => $user->id,
            'customer_type' => CustomerType::PERSONAL->value,
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
        ]);
    }

    // ──────────────────────────────────────────────────────
    // 2. Client entreprise
    // ──────────────────────────────────────────────────────
    private function createClientCompany(User $user, array $input): void
    {
        $org = $this->createOrganization(
            name: $input['company_name'] ?? $input['name'],
            type: OrganizationType::CLIENT_COMPANY,
            tva: $input['tva_number'] ?? null,
            email: $input['email'],
        );

        CustomerProfile::create([
            'user_id' => $user->id,
            'customer_type' => CustomerType::COMPANY->value,
            'plan_type' => 'standard',
            'plan_status' => 'inactive',
        ]);

        $this->addOwner($user, $org);

        // `forceFill` : le rattachement à une organisation n'est pas assignable en masse — c'est
        // l'organisation dont on lira ensuite les données. Ici l'identifiant vient de
        // l'organisation qu'on vient de créer, jamais du corps de la requête.
        $user->forceFill([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ])->save();
    }

    // ──────────────────────────────────────────────────────
    // 3. Prestataire indépendant
    // ──────────────────────────────────────────────────────
    /**
     * @param  array<string, mixed>  $input
     */
    private function createProviderIndependent(User $user, array $input): void
    {
        $profile = ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'pending',
            'verification_status' => 'unverified',
        ]);

        $this->marquerLInscriptionEnLibreService($profile);

        $this->attachTrades($user, $input);
        $this->provisionDefaultAvailability($user);
    }

    /** `self_registered_at` — LA COLONNE QUI PORTE L'ATTENTE D'APPROBATION. */
    private function marquerLInscriptionEnLibreService(ProviderProfile $profile): void
    {
        $profile->forceFill(['self_registered_at' => now()])->save();
    }

    // ──────────────────────────────────────────────────────
    // 4. Prestataire société
    // ──────────────────────────────────────────────────────
    private function createProviderCompany(User $user, array $input): void
    {
        $org = $this->createOrganization(
            name: $input['provider_company_name'] ?? $input['name'],
            type: OrganizationType::PROVIDER_COMPANY,
            tva: $input['tva_number'] ?? null,
            email: $input['email'],
        );

        $profile = ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'pending',
            'verification_status' => 'unverified',
        ]);

        $this->marquerLInscriptionEnLibreService($profile);

        $this->attachTrades($user, $input);
        $this->provisionDefaultAvailability($user);

        $this->addOwner($user, $org);

        // Même raison qu'en `createClientCompany` : ces deux colonnes désignent l'organisation
        // dont le compte lira les données, elles ne sont plus assignables en masse.
        $user->forceFill([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    /** LA SEMAINE PAR DÉFAUT, POSÉE À L'INSCRIPTION. */
    private function provisionDefaultAvailability(User $user): void
    {
        app(DefaultAvailabilityProvisioner::class)->provision($user);
    }

    /**
     * La couverture déclarée à l'inscription : métiers ET zones.
     *
     * @param  array<string, mixed>  $input
     */
    private function attachTrades(User $user, array $input): void
    {
        $tradeIds = array_values(array_filter(array_map('intval', (array) ($input['trade_ids'] ?? []))));
        $zoneIds = array_values(array_filter(array_map('intval', (array) ($input['zone_ids'] ?? []))));

        if ($tradeIds === [] && $zoneIds === []) {
            return;
        }

        app(ProviderCoverageWriter::class)->sync($user, $tradeIds, $zoneIds);
    }

    // ──────────────────────────────────────────────────────
    // Helpers privés
    // ──────────────────────────────────────────────────────
    private function createOrganization(
        string $name,
        OrganizationType $type,
        ?string $tva,
        string $email
    ): OrganizationAccount {
        $baseSlug = Str::slug($name) ?: Str::random(8);
        $slug = $baseSlug;
        $i = 1;

        while (OrganizationAccount::where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$i++;
        }

        return OrganizationAccount::create([
            'name' => $name,
            'legal_name' => $name,
            'slug' => $slug,
            'type' => $type->value,
            'tva_number' => $tva,
            'email' => $email,
            'status' => 'active',
        ]);
    }

    private function addOwner(User $user, OrganizationAccount $org): OrganizationMember
    {
        return OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }
}
