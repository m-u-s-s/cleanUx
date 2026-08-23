<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Les actions GLOBALES : celles qui ne portent sur aucune ligne. POURQUOI LE MOTEUR EN MANQUAIT. */
class ConsoleGlobalActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Sanctum::actingAs(User::factory()->adminComplet()->create([
            'access_scope' => User::ACCESS_SCOPE_ALL,
        ]), ['*']);
    }

    public function test_le_descripteur_annonce_ses_actions_globales(): void
    {
        $reponse = $this->getJson('/api/admin/console/fx')->assertOk();

        // Le mobile ne peut dessiner un bouton que pour ce que le serveur a DÉCLARÉ : sans cette
        // liste, l'action existerait sans que rien ne l'atteigne.
        $cles = collect($reponse->json('resource.global_actions'))->pluck('key')->all();

        $this->assertContains('refresh-all', $cles);
    }

    public function test_il_execute_une_action_globale(): void
    {
        $this->postJson('/api/admin/console/fx/actions/refresh-all')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_une_action_globale_inconnue_rend_404(): void
    {
        $this->postJson('/api/admin/console/fx/actions/inventee')->assertStatus(404);
    }

    public function test_un_lecteur_seul_ne_lance_aucune_action_globale(): void
    {
        Sanctum::actingAs(User::factory()->create([
            'role' => 'admin', 'platform_role' => 'admin', 'access_scope' => 'readonly',
        ]), ['*']);

        // Une action globale touche TOUTE une table : c'est le dernier endroit où l'on veut
        // laisser passer un compte destiné à consulter.
        $this->postJson('/api/admin/console/fx/actions/refresh-all')->assertForbidden();
    }

    public function test_l_action_globale_ne_se_confond_pas_avec_une_ligne(): void
    {
        // `/{resource}/actions/{action}` et `/{resource}/{id}/actions/{action}` se ressemblent assez pour que le routeur prenne « actions » pour un identifiant.
        $this->postJson('/api/admin/console/fx/actions/refresh-all')->assertOk();
    }
}
