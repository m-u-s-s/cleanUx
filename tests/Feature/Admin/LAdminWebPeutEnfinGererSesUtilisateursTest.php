<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\GestionUtilisateurs;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LA ROUTE S'APPELAIT « manage » ET NE MENAIT NULLE PART OÙ GÉRER.
 *
 * `/admin/utilisateurs` rendait `UtilisateursAdmin` : une liste en LECTURE SEULE de 48 lignes.
 * `GestionUtilisateurs` — 266 lignes, activation, changement de rôle, permissions, zone — existait
 * et n'était routé NULLE PART. Un administrateur web ne pouvait donc ni suspendre un compte ni
 * changer un rôle ; la console NATIVE, elle, le pouvait par `UserResource`.
 *
 * Même famille que tout ce chantier : une règle ou une capacité présente sur une surface et
 * absente de l'autre.
 */
class LAdminWebPeutEnfinGererSesUtilisateursTest extends TestCase
{
    use RefreshDatabase;

    /**
     * `EnforceModuleGate` garde cet ecran sur la capacite `manage-users` : un administrateur sans
     * elle recoit 403, et un test qui l'oublie mesurerait cette garde-la au lieu de la sienne.
     */
    private function administrateur(): User
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $admin->forceFill([
            'platform_role' => 'admin',
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'permissions' => ['manage-users', 'perform-critical-admin-actions'],
        ])->save();

        return $admin->refresh();
    }

    public function test_la_page_des_utilisateurs_mene_a_l_ecran_qui_gere(): void
    {
        $this->actingAs($this->administrateur())
            ->get(route('admin.utilisateurs.manage'))
            ->assertOk()
            ->assertSeeLivewire(GestionUtilisateurs::class);
    }

    /** Un administrateur suspend enfin un compte depuis le web. C'est la capacité qui manquait. */
    public function test_un_administrateur_desactive_un_compte(): void
    {
        $cible = User::factory()->client()->create(['is_active' => true]);

        Livewire::actingAs($this->administrateur())
            ->test(GestionUtilisateurs::class)
            ->call('toggleActivation', $cible->id);

        $this->assertFalse((bool) $cible->fresh()->is_active);
    }

    /** Et il change un rôle — l'autre écriture que la liste en lecture seule ne permettait pas. */
    public function test_un_administrateur_change_un_role(): void
    {
        $cible = User::factory()->client()->create();

        Livewire::actingAs($this->administrateur())
            ->test(GestionUtilisateurs::class)
            ->call('updateRole', $cible->id, 'employe');

        $this->assertSame('employe', $cible->fresh()->role);
    }

    /**
     * LE REFUS PAR LA ROUTE. C'est `CheckRole:admin` qui ferme ici — verifie en retirant le trait
     * du composant : ces cas restent verts. Les deux couches se testent donc separement, sinon
     * l'une passerait au vert en mesurant l'autre.
     */
    public function test_un_non_administrateur_est_refuse_par_la_route(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('admin.utilisateurs.manage'))
            ->assertForbidden();
    }

    /** Le témoin du refus : le même écran, ouvert par un administrateur, répond. */
    public function test_temoin_le_meme_ecran_repond_a_un_administrateur(): void
    {
        $this->actingAs($this->administrateur())
            ->get(route('admin.utilisateurs.manage'))
            ->assertOk();
    }

    /** La page doit annoncer ce qu'elle est : son titre n'était qu'un `<h3>`. */
    public function test_la_page_annonce_ce_qu_elle_est(): void
    {
        $reponse = $this->actingAs($this->administrateur())
            ->get(route('admin.utilisateurs.manage'))
            ->assertOk();

        $this->assertMatchesRegularExpression('/<h1[\s>]/i', $reponse->getContent() ?: '');
    }
}
