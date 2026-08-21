<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * LA CAPACITÉ D'UN MODULE DOIT FERMER LA MÊME PORTE DES DEUX CÔTÉS.
 *
 * `EnforceModuleGate` garde les 86 modules d'administration sur le web : un
 * administrateur limité à `manage-quality` reçoit 403 sur `/admin/accounting-v2`.
 *
 * La pile API ne le portait pas. Le même administrateur, avec le jeton de
 * l'application mobile, atteignait `/api/admin/accounting-v2/entries` — la même
 * comptabilité, sans la garde. La restriction n'était donc qu'une gêne
 * d'affichage.
 *
 * Le témoin positif est indispensable : sans lui, un test « c'est refusé »
 * passerait au vert simplement parce que la route est cassée ou le jeton
 * invalide.
 */
class CapaciteAdminSurApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(array $permissions): User
    {
        return User::factory()->create([
            'platform_role' => 'admin',
            'role' => 'admin',
            'permissions' => $permissions,
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /** TÉMOIN POSITIF — l'administrateur qui a la capacité passe. */
    public function test_temoin_l_administrateur_habilite_atteint_la_comptabilite(): void
    {
        Sanctum::actingAs($this->admin(['manage-accounting']), ['*']);

        $reponse = $this->getJson('/api/admin/accounting-v2/entries');

        $this->assertNotSame(403, $reponse->status(),
            "L'administrateur habilité doit franchir la garde de module");
    }

    /** REFUS — l'administrateur sans la capacité est refusé, comme sur le web. */
    public function test_un_administrateur_sans_la_capacite_est_refuse(): void
    {
        Sanctum::actingAs($this->admin(['manage-quality']), ['*']);

        $this->getJson('/api/admin/accounting-v2/entries')->assertForbidden();
    }

    /** TÉMOIN — le super-administrateur n'est jamais bridé. */
    public function test_temoin_le_super_administrateur_passe(): void
    {
        $super = User::factory()->create([
            'platform_role' => 'super_admin',
            'role' => 'super_admin',
            'permissions' => [],
            'two_factor_confirmed_at' => now(),
        ]);
        Sanctum::actingAs($super, ['*']);

        $reponse = $this->getJson('/api/admin/accounting-v2/entries');

        $this->assertNotSame(403, $reponse->status(),
            'Le super-administrateur ne doit être bridé par aucune capacité');
    }

    /** CONTRÔLE WEB — la même restriction ferme déjà la porte côté navigateur. */
    public function test_controle_le_web_refuse_deja_le_meme_administrateur(): void
    {
        $this->actingAs($this->admin(['manage-quality']))
            ->get('/admin/accounting-v2')
            ->assertForbidden();
    }
}
