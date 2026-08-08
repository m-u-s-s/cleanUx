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
         * `company_worker`, ET NON `company` — corrigé le 2026-08-08.
         *
         * Cette assertion figeait la valeur que l'API posait, et cette valeur n'était lue par
         * PERSONNE : `isProviderCompanyWorker()` ne reconnaît que `company_worker`, et
         * `isEmploye()` en dépend. Le fondateur inscrit depuis le mobile ne résolvait donc ni en
         * société ni en prestataire, et retombait sur le repli `client_individuelle` — patron de
         * société traité en particulier, sans ses missions, alors que son organisation existait et
         * qu'il y portait `owner` (les deux assertions ci-dessus le montrent).
         *
         * L'inscription WEB posait déjà `company_worker` pour le même choix. Le test disait donc
         * vrai sur ce que le code faisait, et faux sur ce qu'il devait faire.
         */
        $this->assertSame('company_worker', $profile->provider_type->value);
        $this->assertSame($org->id, $profile->organization_account_id);
        $this->assertNotNull($profile->self_registered_at);
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
