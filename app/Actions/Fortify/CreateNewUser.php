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

            'tva_number' => ['nullable', 'string', 'max:50'],

            // Champs prestataire société
            'provider_company_name' => [
                'required_if:account_type,provider_company',
                'nullable',
                'string',
                'max:255',
            ],

            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature()
                ? ['accepted', 'required']
                : ['nullable'],
        ])->validate();

        return DB::transaction(function () use ($input) {
            /*
             * Important :
             * Le test Jetstream standard n’envoie pas account_type.
             * Donc on met client_personal par défaut.
             */
            $accountType = $input['account_type'] ?? 'client_personal';

            // ── Créer l'utilisateur de base ──
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],

                /*
                 * Important :
                 * Si User.php contient le cast 'password' => 'hashed',
                 * il ne faut PAS faire Hash::make ici.
                 */
                'password' => $input['password'],

                'account_type' => $accountType,

                'role' => $input['role'] ?? 'client',
                'platform_role' => $input['platform_role'] ?? 'client',

                'locale' => app()->getLocale() === 'nl' ? 'nl_BE' : 'fr_BE',
                'timezone' => 'Europe/Brussels',

                'status' => 'active',
                'is_active' => true,
            ]);

            // ── Créer le profil selon le type ──
            match ($accountType) {
                'client', 'client_personal' => $this->createClientPersonal($user),

                'client_company' => $this->createClientCompany($user, $input),

                'provider_independent' => $this->createProviderIndependent($user),

                'provider_company' => $this->createProviderCompany($user, $input),

                default => $this->createClientPersonal($user),
            };

            $this->attachReferral($user, $input);

            return $user;
        });
    }

    private function attachReferral(User $user, array $input): void
    {
        $service = app(\App\Services\Promotion\ReferralService::class);

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

        $user->update([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);
    }

    // ──────────────────────────────────────────────────────
    // 3. Prestataire indépendant
    // ──────────────────────────────────────────────────────
    private function createProviderIndependent(User $user): void
    {
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'pending',
            'verification_status' => 'unverified',
        ]);
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

        ProviderProfile::create([
            'user_id' => $user->id,
            'organization_account_id' => $org->id,
            'provider_type' => ProviderType::COMPANY_WORKER->value,
            'status' => 'pending',
            'verification_status' => 'unverified',
        ]);

        $this->addOwner($user, $org);

        $user->update([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);
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
            $slug = $baseSlug . '-' . $i++;
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