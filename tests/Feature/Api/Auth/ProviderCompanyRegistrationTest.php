<?php

namespace Tests\Feature\Api\Auth;

use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'app prestataire propose deux inscriptions : indépendant ou société.
 *
 * Une société prestataire n'est pas un simple drapeau sur le compte : la plateforme la modélise
 * par un OrganizationAccount de type `provider_company`, dont le signataire devient membre
 * `owner`. C'est ce que consomment déjà l'espace web provider-company (dispatch, gestion
 * d'équipe) et le rattachement des missions via `provider_organization_id`. Inscrire une société
 * sans créer cette organisation produirait un compte incapable d'avoir la moindre équipe.
 */
class ProviderCompanyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_as_a_company_creates_the_organization_and_makes_the_signatory_its_owner(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'provider_kind' => 'company',
            'company_name' => 'Nettoyage Dupont SPRL',
            // Numéro d'entreprise réel : la clé de contrôle est vérifiée depuis que
            // `vat_number` n'est plus accepté sans examen. « BE0123456789 », employé ici
            // à l'origine, n'a jamais été un numéro valide.
            'vat_number' => 'BE0202239951',
        ]))->assertCreated();

        $user = User::where('email', 'nouveau@prestataire.test')->firstOrFail();
        $this->assertSame('employe', $user->role);

        $org = OrganizationAccount::where('name', 'Nettoyage Dupont SPRL')->firstOrFail();
        $this->assertSame('provider_company', $org->type);
        $this->assertSame('pending', $org->status);
        $this->assertSame('BE0202239951', $org->tva_number);

        $member = OrganizationMember::where('organization_account_id', $org->id)
            ->where('user_id', $user->id)
            ->firstOrFail();
        $this->assertSame('owner', $member->role->value);
        $this->assertSame('active', $member->status);

        $profile = ProviderProfile::where('user_id', $user->id)->firstOrFail();
        /*
         * `company_worker`, ET NON `company` COMME CETTE LIGNE L'AFFIRMAIT (corrigé le 2026-08-07).
         *
         * Cette assertion figeait ce que le code ÉCRIVAIT, pas ce dont il a besoin.
         * `ProviderType::COMPANY` n'était lu nulle part dans `app/` — une seule écriture, à
         * l'inscription, et aucune lecture. Les deux vérifications qui décident réellement de
         * l'accès testent l'autre valeur : `isProviderCompanyWorker()`, garde du tableau de bord
         * société, et `isEmploye()`, dont dépendent les routes `role:employe`.
         *
         * Le fondateur d'une société était donc refusé sur l'espace de sa propre société, tandis
         * que chaque employé qu'il invitait recevait `company_worker` de
         * `OrganizationMembershipService`. Le patron était le seul membre du mauvais type, et ce
         * test vert le garantissait.
         */
        $this->assertSame('company_worker', $profile->provider_type->value);
        $this->assertSame($org->id, $profile->organization_account_id);
        $this->assertNotNull($profile->self_registered_at);

        // Le compte PORTE son organisation, il ne fait pas qu'y appartenir : les composants
        // Livewire de l'espace société lisent `current_organization_id` en direct.
        $this->assertSame($org->id, $user->fresh()->organization_account_id);
        $this->assertSame($org->id, $user->fresh()->current_organization_id);
    }

    public function test_registering_as_an_independent_creates_no_organization(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'provider_kind' => 'independent',
        ]))->assertCreated();

        $user = User::where('email', 'nouveau@prestataire.test')->firstOrFail();
        $profile = ProviderProfile::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('independent', $profile->provider_type->value);
        $this->assertNull($profile->organization_account_id);
        $this->assertSame(0, OrganizationAccount::count());
    }

    /**
     * Le type de prestataire est optionnel : l'app cliente n'envoie rien, et une version
     * antérieure de l'app prestataire non plus. On retombe sur l'indépendant, jamais sur une
     * société fantôme sans nom.
     */
    public function test_omitting_the_provider_kind_falls_back_to_independent(): void
    {
        $this->postJson('/api/auth/register', $this->payload())->assertCreated();

        $user = User::where('email', 'nouveau@prestataire.test')->firstOrFail();
        $this->assertSame('independent', ProviderProfile::where('user_id', $user->id)->firstOrFail()->provider_type->value);
        $this->assertSame(0, OrganizationAccount::count());
    }

    public function test_a_company_registration_without_a_company_name_is_rejected(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'provider_kind' => 'company',
        ]))->assertStatus(422)->assertJsonValidationErrors('company_name');

        $this->assertSame(0, User::where('email', 'nouveau@prestataire.test')->count());
    }

    /**
     * `organization_accounts.slug` porte un index unique : deux sociétés homonymes qui
     * s'inscrivent ne doivent pas faire échouer la seconde inscription.
     */
    public function test_two_companies_sharing_a_name_both_register(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'provider_kind' => 'company',
            'company_name' => 'Nettoyage Express',
        ]))->assertCreated();

        $this->postJson('/api/auth/register', $this->payload([
            'email' => 'second@prestataire.test',
            'provider_kind' => 'company',
            'company_name' => 'Nettoyage Express',
        ]))->assertCreated();

        $slugs = OrganizationAccount::pluck('slug');
        $this->assertCount(2, $slugs);
        $this->assertCount(2, $slugs->unique(), 'Les slugs doivent rester distincts.');
    }

    /**
     * Une société auto-inscrite est soumise à la même attente d'approbation qu'un indépendant.
     */
    public function test_a_self_registered_company_cannot_reach_the_rest_of_the_provider_surface(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'provider_kind' => 'company',
            'company_name' => 'Nettoyage Dupont SPRL',
        ]))->assertCreated();

        $user = User::where('email', 'nouveau@prestataire.test')->firstOrFail();

        $this->actingAs($user, 'sanctum')->getJson('/api/provider/wallet/balance')->assertForbidden();
        $this->actingAs($user, 'sanctum')->getJson('/api/provider/onboarding/progress')->assertOk();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Nouveau Prestataire',
            'email' => 'nouveau@prestataire.test',
            'password' => 'motdepasse123',
            'password_confirmation' => 'motdepasse123',
            'accept_terms' => true,
            'account_type' => 'provider',
        ], $overrides);
    }
}
