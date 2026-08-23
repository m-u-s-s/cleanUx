<?php

namespace Database\Seeders;

use App\Enums\CustomerType;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\ProviderType;
use App\Models\CustomerProfile;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use Database\Seeders\Concerns\BoucleLeDossierPrestataire;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** QaAccountsSeeder — versioned, idempotent provisioning of the 5 QA accounts used by the visual-QA harness (`tools/visual-qa/`) and the embed render sweep (`scripts/embed_sweep.php`). */
class QaAccountsSeeder extends Seeder
{
    use BoucleLeDossierPrestataire;

    /** Shared QA password for all harness accounts. */
    /** Le mot de passe commun à tous les comptes semés — voir `config/brio.php`. */
    private static function motDePasse(): string
    {
        return (string) config('brio.seed.password');
    }

    public function run(): void
    {
        $providerOrg = $this->seedOrganization(
            slug: 'qa-provider-co',
            name: 'QA Provider Co',
            type: OrganizationType::PROVIDER_COMPANY,
            legalName: 'QA Provider Co SRL',
            tvaNumber: 'BE0900000001',
            email: 'team@qa-provider-co.test',
        );

        $clientOrg = $this->seedOrganization(
            slug: 'qa-client-co',
            name: 'QA Client Co',
            type: OrganizationType::CLIENT_COMPANY,
            legalName: 'QA Client Co SA',
            tvaNumber: 'BE0900000002',
            email: 'ops@qa-client-co.test',
        );

        // ── 1. Admin ──────────────────────────────────────────────
        // Full super-admin so the harness reaches all 71 admin pages.
        // The manually-provisioned account had is_super_admin=0 / access_scope='own',
        // which 403'd the permission-gated admin modules (modules/services/teams-
        // partners). We codify the FULL-scope state the admin sweep actually needs.
        $this->seedUser(
            email: 'admin@brio.test',
            name: 'QA Admin',
            platformRole: 'admin',
            role: 'admin',
            isSuperAdmin: true,
            accessScope: 'full',
        );

        // ── 2. Provider company OWNER ─────────────────────────────
        $providerCompanyUser = $this->seedUser(
            email: 'qa-provider-company@brio.test',
            name: 'QA Provider Company',
            platformRole: 'provider_company',
            role: 'employe',
            currentOrganizationId: $providerOrg->id,
            organizationAccountId: $providerOrg->id,
        );
        $this->seedMembership($providerOrg, $providerCompanyUser, OrganizationRole::OWNER);
        $this->declareUnMetier($providerCompanyUser);
        $this->boucleLeDossier($providerCompanyUser);
        $this->seedProviderProfile(
            $providerCompanyUser,
            ProviderType::COMPANY_WORKER,
            organizationAccountId: $providerOrg->id,
        );

        // ── 3. Client company OWNER ───────────────────────────────
        $clientCompanyUser = $this->seedUser(
            email: 'dominique.monnier@example.org',
            name: 'Dominique Monnier',
            platformRole: 'entreprise',
            role: 'user',
            currentOrganizationId: $clientOrg->id,
            organizationAccountId: $clientOrg->id,
        );
        $this->seedMembership($clientOrg, $clientCompanyUser, OrganizationRole::OWNER);
        $this->seedCustomerProfile(
            $clientCompanyUser,
            CustomerType::COMPANY,
            planType: 'business',
            planStatus: 'active',
        );

        // ── 4. Independent provider ───────────────────────────────
        $providerUser = $this->seedUser(
            email: 'bsanchez@example.org',
            name: 'B. Sanchez',
            platformRole: 'employe',
            role: 'employe',
        );
        $this->seedProviderProfile(
            $providerUser,
            ProviderType::INDEPENDENT,
            organizationAccountId: null,
        );

        // Le harnais visuel parcourt l'espace prestataire : un dossier d'inscription non bouclé
        // l'arrêterait sur le mur du KYC, et chaque module de cet espace serait relevé en échec.
        $this->declareUnMetier($providerUser);
        $this->boucleLeDossier($providerUser);

        // ── 5. Personal client ────────────────────────────────────
        $clientUser = $this->seedUser(
            email: 'lemoine.gabrielle@example.net',
            name: 'Gabrielle Lemoine',
            platformRole: 'client',
            role: 'client',
        );
        $this->seedCustomerProfile(
            $clientUser,
            CustomerType::PERSONAL,
            planType: 'standard',
            planStatus: 'active',
        );

        $this->command?->info('✅ QaAccountsSeeder: 5 visual-QA accounts provisioned (idempotent).');
    }

    /** Create or update a QA organization, keyed on its deterministic slug. */
    private function seedOrganization(
        string $slug,
        string $name,
        OrganizationType $type,
        string $legalName,
        string $tvaNumber,
        string $email,
    ): OrganizationAccount {
        return OrganizationAccount::updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'legal_name' => $legalName,
                'type' => $type->value,
                'tva_number' => $tvaNumber,
                'email' => $email,
                'status' => 'active',
                'is_multisite' => false,
                'is_key_account' => false,
                'metadata' => ['seeded' => true, 'qa' => true],
            ],
        );
    }

    /** Un métier déclaré — sans quoi l'étape « Déclarer vos métiers » refuse, et le dispatch ne rendrait ce compte candidat à rien. */
    private function declareUnMetier(User $utilisateur): void
    {
        $metier = Trade::query()->where('slug', 'nettoyage')->where('is_active', true)->first();

        if ($metier) {
            $utilisateur->trades()->syncWithoutDetaching([$metier->id => ['is_primary' => true]]);
        }
    }

    /** Create or update a QA user, keyed on email. */
    private function seedUser(
        string $email,
        string $name,
        string $platformRole,
        string $role,
        ?int $currentOrganizationId = null,
        ?int $organizationAccountId = null,
        bool $isSuperAdmin = false,
        string $accessScope = 'own',
    ): User {
        // Le numéro est DÉRIVÉ de l'adresse, donc stable d'un semis à l'autre : `ProfileComplete`
        // exige un téléphone, et un compte QA sans numéro bloque son parcours dès la première
        // étape — le harnais relèverait alors tout l'espace prestataire en échec.
        $telephone = '+3247'.substr((string) sprintf('%07d', crc32($email) % 10000000), 0, 7);

        $utilisateur = User::firstOrNew(['email' => $email]);

        // `forceFill` ET NON `updateOrCreate` : `platform_role`, `role`, `current_organization_id` et `organization_account_id` ne sont plus assignables en masse — ce sont les colonnes qu'une inscription publique ne doit jamais pouvoir se poser elle-même.
        $utilisateur->forceFill([
            'name' => $name,
            'phone' => $telephone,
            'password' => Hash::make(self::motDePasse()),
            'platform_role' => $platformRole,
            'role' => $role,
            'account_type' => 'client_personal',
            'status' => 'active',
            'is_active' => true,
            'locale' => 'fr_BE',
            'timezone' => 'Europe/Brussels',
            'email_verified_at' => now(),
            'current_organization_id' => $currentOrganizationId,
            'organization_account_id' => $organizationAccountId,
            'is_super_admin' => $isSuperAdmin,
            'access_scope' => $accessScope,
        ])->save();

        return $utilisateur;
    }

    /** Create or update the membership linking a user to an org, keyed on (org, user). */
    private function seedMembership(
        OrganizationAccount $org,
        User $user,
        OrganizationRole $role,
    ): OrganizationMember {
        return OrganizationMember::updateOrCreate(
            [
                'organization_account_id' => $org->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $role->value,
                'status' => 'active',
                'permissions' => [],
                'joined_at' => now(),
            ],
        );
    }

    /** Create or update a provider profile, keyed on user. */
    private function seedProviderProfile(
        User $user,
        ProviderType $type,
        ?int $organizationAccountId,
    ): ProviderProfile {
        return ProviderProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'organization_account_id' => $organizationAccountId,
                'provider_type' => $type->value,
                'status' => 'active',
                'verification_status' => 'verified',
            ],
        );
    }

    /** Create or update a customer profile, keyed on user. */
    private function seedCustomerProfile(
        User $user,
        CustomerType $type,
        string $planType,
        string $planStatus,
    ): CustomerProfile {
        return CustomerProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'customer_type' => $type->value,
                'plan_type' => $planType,
                'plan_status' => $planStatus,
                'preferences' => ['seeded' => true, 'qa' => true],
            ],
        );
    }
}
