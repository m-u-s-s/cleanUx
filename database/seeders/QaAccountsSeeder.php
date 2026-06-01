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
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * QaAccountsSeeder — versioned, idempotent provisioning of the 5 QA accounts
 * used by the visual-QA harness (`tools/visual-qa/`) and the embed render sweep
 * (`scripts/embed_sweep.php`).
 *
 * These accounts log in per role (password `QaPhase2!`) so the harness can sweep
 * every embedded page by role and assert role-scoped pages render 200 (not 403).
 * They were originally provisioned by hand on the dev MySQL DB; this seeder
 * versions that EXACT known-working state so it can be reproduced on CI / a fresh
 * machine without manual steps.
 *
 * The 5 roles (cf. tools/visual-qa/modules.mjs CREDENTIALS):
 *   - admin@cleanux.test               → platform admin (platform_role=admin)
 *   - qa-provider-company@cleanux.test → OWNER of a PROVIDER_COMPANY org
 *                                        (current_organization_id set → dispatch/equipe/canaux)
 *   - dominique.monnier@example.org    → OWNER of a CLIENT_COMPANY org
 *                                        (current_organization_id set → contrats/membres/facturation)
 *   - bsanchez@example.org             → independent provider (ProviderProfile INDEPENDENT)
 *   - lemoine.gabrielle@example.net    → personal client (CustomerProfile PERSONAL)
 *
 * Admin determination: User::isAdmin() reads `platform_role IN (admin, super_admin)`
 * first, with a legacy `role` column fallback. We set BOTH (platform_role=admin,
 * role=admin) to satisfy either path.
 *
 * Org-scoped permissions (e.g. missions.dispatch) derive from the OWNER role via
 * PermissionService, so the member `permissions` array stays empty `[]` — matching
 * the inspected DB state.
 *
 * Idempotent: every write goes through updateOrCreate keyed on a deterministic
 * identifier (email / org slug / (org,user) / user). Re-running produces no
 * duplicates and no unique-constraint errors.
 *
 * Self-contained: it creates the two QA orgs if absent — it does NOT depend on
 * any demo seeder. NOT wired into DatabaseSeeder (QA accounts must never reach the
 * production seed path). Run explicitly:
 *
 *     php artisan db:seed --class=QaAccountsSeeder
 */
class QaAccountsSeeder extends Seeder
{
    /** Shared QA password for all harness accounts. */
    private const QA_PASSWORD = 'QaPhase2!';

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
            email: 'admin@cleanux.test',
            name: 'QA Admin',
            platformRole: 'admin',
            role: 'admin',
            isSuperAdmin: true,
            accessScope: 'full',
        );

        // ── 2. Provider company OWNER ─────────────────────────────
        $providerCompanyUser = $this->seedUser(
            email: 'qa-provider-company@cleanux.test',
            name: 'QA Provider Company',
            platformRole: 'provider_company',
            role: 'employe',
            currentOrganizationId: $providerOrg->id,
            organizationAccountId: $providerOrg->id,
        );
        $this->seedMembership($providerOrg, $providerCompanyUser, OrganizationRole::OWNER);
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

    /**
     * Create or update a QA organization, keyed on its deterministic slug.
     */
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

    /**
     * Create or update a QA user, keyed on email. Password is always reset to the
     * shared QA password so the harness keeps working after a re-run.
     */
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
        return User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::QA_PASSWORD),
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
            ],
        );
    }

    /**
     * Create or update the membership linking a user to an org, keyed on
     * (org, user). Org-scoped permissions derive from the role via
     * PermissionService, so the explicit permissions array stays empty.
     */
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

    /**
     * Create or update a provider profile, keyed on user.
     */
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

    /**
     * Create or update a customer profile, keyed on user.
     */
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
