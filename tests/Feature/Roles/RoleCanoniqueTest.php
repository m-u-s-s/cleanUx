<?php

namespace Tests\Feature\Roles;

use App\Enums\CustomerType;
use App\Enums\OrganizationRole;
use App\Enums\ProviderType;
use App\Enums\Role;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LES SIX RÔLES DE LA PLATEFORME, ET UNE SEULE FAÇON DE LES LIRE.
 *
 * POURQUOI CE FICHIER EXISTE. Le rôle d'un compte se déduisait de CINQ signaux répartis dans autant
 * d'endroits : `platform_role`, la colonne `role` (héritée), `customer_type`, `provider_type` et le
 * type de l'organisation courante. 217 appels dans 65 fichiers interrogeaient ces signaux
 * séparément, chacun avec sa propre idée de l'ordre de priorité — c'est ainsi que la navbar a
 * montré le menu client à un administrateur pendant toute une livraison.
 *
 * `Role` tranche une fois pour toutes. Les prédicats `is*()` restent : ils deviennent
 * l'implémentation, plus la décision.
 *
 * L'ORDRE DE RÉSOLUTION EST LE PROPOS DE CE TEST. Un compte peut satisfaire plusieurs signaux à la
 * fois — un administrateur reste souvent client, un gérant de société intervient parfois sur le
 * terrain. Chaque cas ci-dessous fige une priorité, pas une commodité.
 */
class RoleCanoniqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_six_roles_existent_et_pas_un_de_plus(): void
    {
        $this->assertSame([
            'super_admin',
            'admin',
            'client_individuelle',
            'client_societe',
            'provider_individuelle',
            'provider_societe',
        ], array_map(fn (Role $r) => $r->value, Role::cases()));
    }

    public function test_le_super_admin_prime_sur_tout(): void
    {
        $user = User::factory()->create(['platform_role' => 'super_admin']);

        $this->assertSame(Role::SUPER_ADMIN, $user->roleCanonique());
    }

    public function test_l_admin_vient_ensuite(): void
    {
        $user = User::factory()->admin()->create();

        $this->assertSame(Role::ADMIN, $user->roleCanonique());
    }

    public function test_un_administrateur_aussi_client_reste_administrateur(): void
    {
        /*
         * LE DÉFAUT QUE CE TEST EMPÊCHE DE REVENIR.
         *
         * Promouvoir un client en administrateur ne lui retire pas son profil client. Tant que
         * `isClient()` était testé en premier, le compte gardait le menu client sans le moindre
         * lien vers l'administration, et le changement de rôle semblait sans effet.
         */
        $user = User::factory()->admin()->create();
        $user->customerProfile()->create(['customer_type' => CustomerType::PERSONAL->value]);

        $this->assertSame(Role::ADMIN, $user->fresh()->roleCanonique());
    }

    public function test_un_particulier_est_client_individuelle(): void
    {
        $user = User::factory()->client()->create();

        $this->assertSame(Role::CLIENT_INDIVIDUELLE, $user->roleCanonique());
    }

    public function test_un_membre_de_societe_cliente_est_client_societe(): void
    {
        $org = OrganizationAccount::factory()->clientCompany()->create();
        $user = User::factory()->entreprise()->create(['current_organization_id' => $org->id]);
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        $this->assertSame(Role::CLIENT_SOCIETE, $user->fresh()->roleCanonique());
    }

    public function test_un_prestataire_independant_est_provider_individuelle(): void
    {
        // La fabrique `employe()` ne pose que la colonne héritée : le profil prestataire, qui
        // porte le type, doit être créé explicitement — sans quoi le test mesurerait le repli.
        $user = User::factory()->employe()->create();
        $user->providerProfile()->create(['provider_type' => ProviderType::INDEPENDENT->value]);

        $this->assertSame(Role::PROVIDER_INDIVIDUELLE, $user->fresh()->roleCanonique());
    }

    public function test_un_membre_de_societe_prestataire_est_provider_societe(): void
    {
        $user = User::factory()->employe()->create();
        $user->providerProfile()->create(['provider_type' => ProviderType::COMPANY_WORKER->value]);

        $this->assertSame(Role::PROVIDER_SOCIETE, $user->fresh()->roleCanonique());
    }

    public function test_seul_provider_societe_porte_des_sous_roles(): void
    {
        // Les 11 rôles d'organisation décrivent le travail DANS une société prestataire :
        // répartition, équipes terrain, qualité. Un particulier n'en a aucun.
        $this->assertTrue(Role::PROVIDER_SOCIETE->porteDesSousRoles());
        $this->assertCount(11, Role::PROVIDER_SOCIETE->sousRoles());

        // Les quatre roles releves ensemble : une hierarchie mal declaree touche generalement
        // plusieurs roles a la fois.
        $porteurs = [];

        foreach ([Role::SUPER_ADMIN, Role::ADMIN, Role::CLIENT_INDIVIDUELLE, Role::PROVIDER_INDIVIDUELLE] as $role) {
            if ($role->porteDesSousRoles()) {
                $porteurs[] = $role->value.' : se declare porteur de sous-roles';
            }

            if ($role->sousRoles() !== []) {
                $porteurs[] = $role->value.' : rend ['.implode(', ', $role->sousRoles()).']';
            }
        }

        $this->assertSame([], $porteurs, 'Ces roles ne portent aucun sous-role.');
    }

    public function test_chaque_role_a_son_tableau_de_bord(): void
    {
        // La demande est explicite : les six rôles ont chacun le leur. Un rôle sans route de
        // destination laisserait son titulaire sur la page d'accueil publique après connexion.
        foreach (Role::cases() as $role) {
            $this->assertNotEmpty($role->routeDuTableauDeBord(), $role->value);
        }
    }

    public function test_chaque_role_porte_un_libelle_lisible(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertNotEmpty($role->label(), $role->value);
        }
    }
}
