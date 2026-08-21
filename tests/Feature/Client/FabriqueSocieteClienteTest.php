<?php

namespace Tests\Feature\Client;

use App\Enums\OrganizationRole;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA FABRIQUE DOIT PRODUIRE UNE SOCIÉTÉ CLIENTE QUI TIENT DEBOUT.
 *
 * Vingt-huit composants d'espace société exigent quatre choses simultanément :
 * profil `company`, organisation rattachée, adhésion active, et rattachement
 * lisible par `organizationContextId()`. Les fixtures écrites à la main en
 * oubliaient toujours une — et les 403 qui en résultaient passaient pour des
 * défauts du code.
 *
 * Ce test garde la fabrique elle-même : si elle cesse de produire un compte
 * complet, on l'apprend ici plutôt qu'à travers vingt-huit écrans.
 */
class FabriqueSocieteClienteTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_fabrique_produit_les_quatre_invariants(): void
    {
        $societe = User::factory()->societeCliente()->create();

        $this->assertTrue($societe->isClientCompany(), 'Le profil doit dire « société »');
        $this->assertNotNull($societe->organizationContextId(), 'Le rattachement doit être lisible');
        $this->assertNotNull($societe->organization_account_id, 'La colonne historique doit être posée');
        $this->assertNotNull($societe->current_organization_id, 'La colonne courante doit être posée aussi');

        $this->assertTrue(
            OrganizationMember::query()
                ->where('organization_account_id', $societe->organizationContextId())
                ->where('user_id', $societe->id)
                ->where('status', 'active')
                ->exists(),
            "L'adhésion doit être active"
        );
    }

    /** Elle doit aussi franchir la porte du rôle : l'espace client est gardé par `role:client`. */
    public function test_elle_franchit_la_garde_de_role(): void
    {
        $societe = User::factory()->societeCliente()->create();

        $this->assertTrue($societe->isClient(), 'Une société cliente reste une cliente');
        $this->assertTrue($societe->matchesRole('client'), 'Elle doit satisfaire role:client');
        $this->assertTrue($societe->compteActif(), 'Le compte doit être actif');
    }

    /** Le dirigeant porte le rôle d'organisation qui ouvre les écrans financiers. */
    public function test_le_dirigeant_est_proprietaire_par_defaut(): void
    {
        $societe = User::factory()->societeCliente()->create();

        $adhesion = OrganizationMember::query()
            ->where('user_id', $societe->id)
            ->firstOrFail();

        // `role` est casté en énumération par le modèle : on compare la valeur.
        $this->assertSame(
            OrganizationRole::OWNER->value,
            $adhesion->role instanceof OrganizationRole ? $adhesion->role->value : $adhesion->role
        );
    }

    /** Deux comptes peuvent partager la même organisation — c'est le cas d'une équipe. */
    public function test_deux_membres_partagent_une_organisation(): void
    {
        $dirigeant = User::factory()->societeCliente()->create();
        $org = $dirigeant->currentOrganization ?? $dirigeant->organizationAccount;

        $collegue = User::factory()->societeCliente($org, 'viewer')->create();

        $this->assertSame(
            $dirigeant->organizationContextId(),
            $collegue->organizationContextId(),
            'Les deux doivent pointer la même organisation'
        );
    }

    /** TÉMOIN — un particulier reste un particulier : la fabrique ne teinte pas tout le monde. */
    public function test_temoin_un_client_ordinaire_n_est_pas_une_societe(): void
    {
        $particulier = User::factory()->client()->create();

        $this->assertFalse($particulier->isClientCompany());
        $this->assertNull($particulier->organizationContextId());
    }
}
