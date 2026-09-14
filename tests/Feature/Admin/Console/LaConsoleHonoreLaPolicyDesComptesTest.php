<?php

namespace Tests\Feature\Admin\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DEUX CHEMINS D'ÉCRITURE VERS LA MÊME TABLE `users`, L'UN POLICÉ ET L'AUTRE PAS.
 *
 * `GestionUtilisateurs` passe par `Gate::authorize` et donc par les quatre verrous de `UserPolicy`.
 * Le moteur de console n'en appliquait aucun — et c'est lui que sert l'application mobile.
 */
class LaConsoleHonoreLaPolicyDesComptesTest extends TestCase
{
    use RefreshDatabase;

    private function agitEnTantQue(User $admin): User
    {
        Sanctum::actingAs($admin, ['*']);

        return $admin;
    }

    /** Un administrateur qui a la capacité `manage-users` mais PAS les actions critiques. */
    private function adminSansActionsCritiques(): User
    {
        return User::factory()->admin()->create([
            'permissions' => ['manage-users'],
        ]);
    }

    /**
     * LE TÉMOIN POSITIF. Sans lui, les refus ci-dessous passeraient au vert le jour où plus
     * personne ne pourrait modifier un compte depuis la console.
     */
    #[Test]
    public function un_administrateur_habilite_modifie_bien_un_compte(): void
    {
        $this->agitEnTantQue(User::factory()->adminComplet()->create());
        $cible = User::factory()->create(['name' => 'Avant']);

        $this->patchJson('/api/admin/console/users/'.$cible->id, ['name' => 'Après'])
            ->assertOk();

        $this->assertSame('Après', $cible->fresh()->name);
    }

    #[Test]
    public function un_administrateur_sans_actions_critiques_ne_modifie_pas_un_compte(): void
    {
        $this->agitEnTantQue($this->adminSansActionsCritiques());
        $cible = User::factory()->create(['platform_role' => 'user']);

        $this->patchJson('/api/admin/console/users/'.$cible->id, ['platform_role' => 'admin'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden_policy');

        $this->assertSame('user', $cible->fresh()->platform_role);
    }

    /** Le quatrième verrou de la policy : on ne se modifie pas soi-même. */
    #[Test]
    public function un_administrateur_ne_se_modifie_pas_lui_meme(): void
    {
        $admin = $this->agitEnTantQue(User::factory()->adminComplet()->create());

        $this->patchJson('/api/admin/console/users/'.$admin->id, ['platform_role' => 'user'])
            ->assertStatus(403);

        $this->assertNotSame('user', $admin->fresh()->platform_role);
    }

    #[Test]
    public function un_administrateur_sans_actions_critiques_ne_suspend_pas_un_compte(): void
    {
        $this->agitEnTantQue($this->adminSansActionsCritiques());
        $cible = User::factory()->create(['is_active' => true]);

        $this->postJson('/api/admin/console/users/'.$cible->id.'/actions/suspend')
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden_policy');

        $this->assertTrue((bool) $cible->fresh()->is_active);
    }

    /** LE TÉMOIN POSITIF DE L'ACTION : l'administrateur habilité suspend toujours. */
    #[Test]
    public function un_administrateur_habilite_suspend_bien_un_compte(): void
    {
        $this->agitEnTantQue(User::factory()->adminComplet()->create());
        $cible = User::factory()->create(['is_active' => true]);

        $this->postJson('/api/admin/console/users/'.$cible->id.'/actions/suspend')
            ->assertOk();

        $this->assertFalse((bool) $cible->fresh()->is_active);
    }

    #[Test]
    public function un_administrateur_sans_actions_critiques_ne_supprime_pas_un_compte(): void
    {
        $this->agitEnTantQue($this->adminSansActionsCritiques());
        $cible = User::factory()->create();

        $this->deleteJson('/api/admin/console/users/'.$cible->id)
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden_policy');

        $this->assertDatabaseHas('users', ['id' => $cible->id]);
    }

    /** Les 79 autres descripteurs n'adhèrent pas au contrat : leur comportement ne change pas. */
    #[Test]
    public function temoin_un_descripteur_sans_policy_ecrit_toujours(): void
    {
        $this->agitEnTantQue($this->adminSansActionsCritiques());

        $this->getJson('/api/admin/console/users')->assertOk();
    }
}
