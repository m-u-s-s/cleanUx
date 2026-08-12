<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * QUI PEUT ÉCOUTER « QUI EST EN LIGNE » CHEZ LES PRESTATAIRES.
 *
 * `ProviderPresenceChanged` diffuse sur `providers.presence` la mise en ligne et la mise hors ligne
 * de TOUS les prestataires de la plateforme, sans filtre d'organisation. La callback d'autorisation
 * se lisait sur `users.role`, colonne dépréciée, avec une branche `role === 'dispatcher'` qui ne
 * correspond à aucun compte légitime — `dispatcher` est un rôle d'ORGANISATION, jamais une valeur
 * de `users.role`.
 *
 * Cette branche n'était donc atteignable que par ce qui écrivait `dispatcher` dans cette colonne —
 * et jusqu'au correctif d'assignation en masse, le formulaire public d'inscription le pouvait.
 * Les deux failles se tenaient par la main : c'est la chaîne que ce fichier casse.
 */
class CanalPresencePrestatairesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Les callbacks vivent dans routes/channels.php, chargées comme dans
        // Tests\Feature\Broadcasting\PrivateChannelAuthorizationTest.
        require base_path('routes/channels.php');
    }

    private function tenterAbonnement(): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => 'private-providers.presence',
        ]);
    }

    /**
     * ATTAQUE (c) — un compte ordinaire qui s'est écrit `role = admin`.
     *
     * `forceFill` ici pour reconstituer l'état qu'un attaquant obtenait AVANT le correctif
     * d'assignation en masse : le test doit prouver que même en base, cette valeur n'ouvre plus le
     * canal. Sans le correctif de `routes/channels.php`, la première branche `$user->role === 'admin'`
     * répondait vrai et `assertStatus(403)` tombait — c'est elle qui porte la faille.
     */
    public function test_un_compte_marque_role_admin_ne_peut_pas_ecouter_la_presence(): void
    {
        $intrus = User::factory()->create(['platform_role' => User::PLATFORM_USER]);
        $intrus->forceFill(['role' => 'admin'])->save();

        $this->actingAs($intrus);

        $this->tenterAbonnement()->assertStatus(403);
    }

    /**
     * ATTAQUE (c bis) — la branche `dispatcher`, celle qui ne désignait personne de légitime.
     *
     * Sans le correctif, `$user->role === 'dispatcher'` répondait vrai et le canal s'ouvrait.
     */
    public function test_un_compte_marque_role_dispatcher_ne_peut_pas_ecouter_la_presence(): void
    {
        $intrus = User::factory()->create(['platform_role' => User::PLATFORM_USER]);
        $intrus->forceFill(['role' => 'dispatcher'])->save();

        $this->actingAs($intrus);

        $this->tenterAbonnement()->assertStatus(403);
    }

    public function test_un_client_ordinaire_ne_peut_pas_ecouter_la_presence(): void
    {
        $this->actingAs(User::factory()->client()->create(['platform_role' => User::PLATFORM_USER]));

        $this->tenterAbonnement()->assertStatus(403);
    }

    /**
     * Le pendant positif : un administrateur de plateforme continue d'écouter. Sans lui, on ne
     * saurait pas distinguer « la faille est fermée » de « le canal est mort ».
     */
    public function test_un_administrateur_de_plateforme_ecoute_toujours_la_presence(): void
    {
        // La fabrique `admin()` pose `platform_role = admin` ; `role` n'entre pour rien dans la
        // décision désormais.
        $this->actingAs(User::factory()->admin()->create(['is_active' => true]));

        $this->tenterAbonnement()->assertStatus(200);
    }

    /**
     * Un administrateur désactivé n'écoute plus rien : `canAccessAdminModule()` vérifie `is_active`,
     * ce que l'ancienne lecture de `users.role` ne regardait pas.
     */
    public function test_un_administrateur_desactive_n_ecoute_plus_la_presence(): void
    {
        $suspendu = User::factory()->admin()->create(['is_active' => false]);

        $this->actingAs($suspendu);

        $this->tenterAbonnement()->assertStatus(403);
    }
}
