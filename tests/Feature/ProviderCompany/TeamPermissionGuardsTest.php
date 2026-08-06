<?php

namespace Tests\Feature\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Livewire\ProviderCompany\TeamManagement;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LES PERMISSIONS D'UN MEMBRE NE SE MODIFIENT PAS SANS GARDE.
 *
 * POURQUOI CE FICHIER EXISTE. `TeamManagement::togglePermission()` faisait
 * `OrganizationMember::find($this->editingMemberId)` — un identifiant venu du client, résolu
 * sans aucun scoping d'organisation et sans aucune vérification de permission. Trois trous
 * dans la même méthode :
 *
 *   1. ISOLATION. Rien ne liait le membre visé à l'organisation de l'acteur : un identifiant
 *      appartenant à une AUTRE société était accepté. C'est une fuite entre clients, pas une
 *      gêne d'ergonomie.
 *   2. ESCALADE. La seule garde du composant est au `mount()`, sur `members.invite`. Un membre
 *      autorisé à inviter pouvait donc s'attribuer — ou attribuer — n'importe quelle permission.
 *   3. HIÉRARCHIE. `PermissionService::canManageMember()` existait déjà et n'était appelé nulle
 *      part : rien n'empêchait d'agir sur un membre de rang égal ou supérieur.
 *
 * Le test existant (TeamManagementCoverageBatch8Test) ne couvrait que le cas nominal — un
 * owner agissant sur un worker de sa propre organisation — donc il restait vert malgré tout.
 */
class TeamPermissionGuardsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Le composant trie avec `FIELD()`, fonction MySQL absente de SQLite. On l'enregistre pour
     * le harnais de test uniquement — jamais dans le composant, la config ou les migrations.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $pdo = DB::connection()->getPdo();

        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('FIELD', function ($value, ...$list) {
                $index = array_search($value, $list, false);

                return $index === false ? 0 : $index + 1;
            });
        }
    }

    /** @return array{0: OrganizationAccount, 1: User, 2: OrganizationMember} */
    private function makeCompanyUser(string $role = OrganizationRole::OWNER->value): array
    {
        $org = OrganizationAccount::factory()->create();

        $user = User::factory()->entreprise()->create([
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        $member = OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        return [$org, $user, $member];
    }

    private function makeMember(OrganizationAccount $org, string $role): OrganizationMember
    {
        return OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => User::factory()->create()->id,
            'role' => $role,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);
    }

    #[Test]
    public function un_membre_d_une_autre_organisation_ne_peut_pas_etre_modifie(): void
    {
        [, $patron] = $this->makeCompanyUser(OrganizationRole::OWNER->value);

        $autreOrg = OrganizationAccount::factory()->create();
        $cible = $this->makeMember($autreOrg, OrganizationRole::WORKER->value);

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            ->call('openPermissions', $cible->id)
            ->call('togglePermission', 'analytics.view', true);

        $this->assertNull(
            $cible->fresh()->permissions,
            "Un identifiant appartenant à une AUTRE organisation a été accepté : fuite d'isolation.",
        );
    }

    #[Test]
    public function inviter_ne_donne_pas_le_droit_de_distribuer_les_permissions(): void
    {
        // MANAGER porte `members.invite` — donc passe le mount — sans être propriétaire.
        [$org, $manager] = $this->makeCompanyUser(OrganizationRole::MANAGER->value);
        $cible = $this->makeMember($org, OrganizationRole::WORKER->value);

        Livewire::actingAs($manager)
            ->test(TeamManagement::class)
            ->call('openPermissions', $cible->id)
            ->call('togglePermission', 'analytics.view', true);

        $this->assertNull(
            $cible->fresh()->permissions,
            'Le droit d’inviter suffisait à distribuer n’importe quelle permission : escalade.',
        );
    }

    #[Test]
    public function on_ne_modifie_pas_un_membre_de_rang_superieur_ou_egal(): void
    {
        [$org, $manager] = $this->makeCompanyUser(OrganizationRole::MANAGER->value);
        $pair = $this->makeMember($org, OrganizationRole::MANAGER->value);

        Livewire::actingAs($manager)
            ->test(TeamManagement::class)
            ->call('openPermissions', $pair->id)
            ->call('togglePermission', 'analytics.view', true);

        $this->assertNull(
            $pair->fresh()->permissions,
            'La garde hiérarchique `canManageMember()` existait déjà mais n’était appelée nulle part.',
        );
    }

    #[Test]
    public function la_modale_ne_montre_pas_un_membre_d_une_autre_organisation(): void
    {
        // Fermer l'ÉCRITURE ne suffit pas : `getEditingMemberProperty()` chargeait le membre sans
        // scoping, donc la modale affichait nom, courriel et photo d'un inconnu. Une fuite en
        // lecture reste une fuite.
        [, $patron] = $this->makeCompanyUser(OrganizationRole::OWNER->value);

        $autreOrg = OrganizationAccount::factory()->create();
        $etranger = $this->makeMember($autreOrg, OrganizationRole::WORKER->value);

        // Assertion sur le NOM, pas sur le courriel : la modale rend `user->name` (ligne 229 de
        // la vue) et jamais l'adresse. Une première version de ce test assertait sur le courriel
        // et passait donc pour une mauvaise raison — verte sans rien vérifier.
        $nom = $etranger->user->name;

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            ->call('openPermissions', $etranger->id)
            ->assertDontSee($nom);
    }

    #[Test]
    public function le_dernier_proprietaire_ne_peut_pas_etre_declasse(): void
    {
        // Une société sans propriétaire actif n'a plus personne pour inviter, facturer ou céder
        // ses droits : l'enfermement serait définitif.
        [$org, $patron, $membrePatron] = $this->makeCompanyUser(OrganizationRole::OWNER->value);

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            ->call('changeRole', $membrePatron->id, OrganizationRole::WORKER->value);

        $this->assertSame(
            OrganizationRole::OWNER->value,
            $membrePatron->fresh()->role->value,
            'Le dernier propriétaire actif a été déclassé : organisation sans gouvernance.',
        );
    }

    #[Test]
    public function un_second_proprietaire_peut_etre_declasse(): void
    {
        // La protection porte sur le DERNIER, pas sur le rôle : tant qu'un autre owner actif
        // existe, la gouvernance est assurée.
        [$org, $patron] = $this->makeCompanyUser(OrganizationRole::OWNER->value);
        $second = $this->makeMember($org, OrganizationRole::OWNER->value);

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            ->call('changeRole', $second->id, OrganizationRole::WORKER->value);

        $this->assertSame(OrganizationRole::WORKER->value, $second->fresh()->role->value);
    }

    #[Test]
    public function le_proprietaire_garde_la_main_sur_son_equipe(): void
    {
        // Le chemin nominal doit rester ouvert : durcir ne veut pas dire bloquer.
        [$org, $patron] = $this->makeCompanyUser(OrganizationRole::OWNER->value);
        $cible = $this->makeMember($org, OrganizationRole::WORKER->value);

        Livewire::actingAs($patron)
            ->test(TeamManagement::class)
            ->call('openPermissions', $cible->id)
            ->call('togglePermission', 'analytics.view', true);

        $this->assertTrue($cible->fresh()->permissions['analytics.view']);
    }
}
