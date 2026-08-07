<?php

namespace Tests\Feature\Api;

use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * S'INSCRIRE COMME SOCIÉTÉ DOIT SUFFIRE À OUVRIR SON ESPACE.
 *
 * POURQUOI CE FICHIER EXISTE. `provider@soc.com` — un compte réel de la base de développement — est
 * né de ce parcours, et n'a jamais pu ouvrir un seul de ses onze écrans société. L'inscription
 * créait bien l'organisation, l'adhésion `owner` et le profil prestataire, puis s'arrêtait à
 * mi-chemin :
 *
 *   1. LES COLONNES D'ORGANISATION DE L'UTILISATEUR restaient vides. Le parcours CLIENT société les
 *      pose (`createClientIdentity`), le parcours PRESTATAIRE l'oubliait — une asymétrie entre deux
 *      méthodes voisines du même fichier.
 *
 *   2. LE PROFIL PORTAIT `provider_type = company`, quand tout le code demande `company_worker` :
 *      `isProviderCompanyWorker()` garde le tableau de bord société, et `isEmploye()` — dont
 *      dépendent les routes `role:employe` — s'appuie sur les deux mêmes valeurs. Le FONDATEUR de
 *      la société était donc refusé sur son propre espace, et `OrganizationMembershipService`
 *      créait déjà ses employés invités avec `company_worker`. Le patron était le seul du mauvais
 *      type.
 *
 * Ce test décrit l'état complet qu'un compte société doit avoir à la seconde où il est créé, sans
 * intervention manuelle et sans migration de rattrapage.
 */
class InscriptionSocietePrestataireTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function payloadSociete(array $surcharges = []): array
    {
        return array_merge([
            'name' => 'Awa Diallo',
            'email' => 'awa@nettoyage-express.test',
            'password' => 'MotDePasse!2026',
            'password_confirmation' => 'MotDePasse!2026',
            'accept_terms' => true,
            'account_type' => 'provider',
            'provider_kind' => 'company',
            'company_name' => 'Nettoyage Express SPRL',
        ], $surcharges);
    }

    private function inscrire(array $surcharges = []): User
    {
        // L'inscription d'une société ouvre sa vérification d'entreprise, mise en file. On ne veut
        // ni l'exécuter ici, ni dépendre d'un registre officiel joignable.
        Queue::fake();

        $this->postJson('/api/auth/register', $this->payloadSociete($surcharges))
            ->assertSuccessful();

        return User::where('email', $this->payloadSociete($surcharges)['email'])->firstOrFail();
    }

    #[Test]
    public function le_compte_porte_son_organisation_des_la_creation(): void
    {
        $patronne = $this->inscrire();

        $org = OrganizationAccount::where('name', 'Nettoyage Express SPRL')->firstOrFail();

        // LES DEUX colonnes, parce que deux familles de code les lisent séparément : les composants
        // Livewire interrogent `current_organization_id` en direct, tandis que `isClientCompany()`
        // passe par `organization_account_id`.
        $this->assertSame($org->id, $patronne->organization_account_id);
        $this->assertSame($org->id, $patronne->current_organization_id);
    }

    #[Test]
    public function la_fondatrice_est_du_meme_type_que_ses_futurs_employes(): void
    {
        $patronne = $this->inscrire();

        $profil = ProviderProfile::where('user_id', $patronne->id)->firstOrFail();

        /*
         * `company_worker`, et non `company`. Ce n'est pas un détail de nommage : c'est la valeur
         * que testent `isProviderCompanyWorker()` — garde du tableau de bord société — et
         * `isEmploye()`, dont dépendent toutes les routes `role:employe`. C'est aussi celle que
         * `OrganizationMembershipService` donne aux employés invités : la fondatrice était le seul
         * membre de sa propre société à ne pas l'avoir.
         */
        $this->assertSame(ProviderType::COMPANY_WORKER->value, $profil->provider_type->value);
        $this->assertTrue($patronne->fresh()->isProviderCompanyWorker());
    }

    #[Test]
    public function elle_est_proprietaire_active_de_sa_societe(): void
    {
        $patronne = $this->inscrire();

        $membre = OrganizationMember::where('user_id', $patronne->id)->firstOrFail();

        $this->assertSame(OrganizationRole::OWNER, $membre->role);
        $this->assertSame('active', $membre->status);
    }

    #[Test]
    public function elle_peut_immediatement_piloter_sa_societe(): void
    {
        /*
         * LE BOUT DE LA CHAÎNE. `can_manage_company` est ce qui ouvre le troisième espace de
         * l'application prestataire ; il se calcule sur `missions.view_all`, que le rôle `owner`
         * porte. Sans les deux corrections ci-dessus, il valait `false` et l'application n'ouvrait
         * que l'espace terrain.
         */
        $patronne = $this->inscrire();

        $org = $patronne->fresh()->currentOrganization;

        $this->assertNotNull($org);
        $this->assertTrue(
            app(PermissionService::class)->can($patronne->fresh(), 'missions.view_all', $org)
        );
    }

    #[Test]
    public function l_api_de_reprise_de_session_le_confirme(): void
    {
        // Ce que l'application lit RÉELLEMENT au démarrage. Les assertions précédentes portent sur
        // la base ; celle-ci porte sur le contrat servi au mobile.
        $patronne = $this->inscrire();

        Sanctum::actingAs($patronne->fresh(), ['*']);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('organization_type', 'provider_company')
            ->assertJsonPath('can_manage_company', true);
    }

    #[Test]
    public function l_espace_societe_repond_des_la_premiere_requete(): void
    {
        $patronne = $this->inscrire();

        Sanctum::actingAs($patronne->fresh(), ['*']);

        $this->getJson('/api/provider/company/overview')->assertOk();
        $this->getJson('/api/provider/company/members')->assertOk();
        $this->getJson('/api/provider/company/field-teams')->assertOk();
    }

    #[Test]
    public function un_prestataire_independant_ne_recoit_pas_d_organisation(): void
    {
        // Le pendant négatif : un indépendant n'a pas de société, et lui en fabriquer une lui
        // donnerait un espace de pilotage vide dont il n'a que faire.
        $solo = $this->inscrire([
            'email' => 'solo@independant.test',
            'provider_kind' => 'independent',
            'company_name' => null,
        ]);

        $this->assertNull($solo->organization_account_id);
        $this->assertNull($solo->current_organization_id);
        $this->assertSame(
            ProviderType::INDEPENDENT->value,
            ProviderProfile::where('user_id', $solo->id)->firstOrFail()->provider_type->value
        );
    }
}
