<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** QUI PEUT ÉCOUTER « QUI EST EN LIGNE » CHEZ LES PRESTATAIRES. */
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

    private function tenterAbonnement(): TestResponse
    {
        return $this->postJson('/broadcasting/auth', [
            'socket_id' => '12345.67890',
            'channel_name' => 'private-providers.presence',
        ]);
    }

    /** ATTAQUE (c) — un compte ordinaire qui s'est écrit `role = admin`. */
    public function test_un_compte_marque_role_admin_ne_peut_pas_ecouter_la_presence(): void
    {
        $intrus = User::factory()->create(['platform_role' => User::PLATFORM_USER]);
        $intrus->forceFill(['role' => 'admin'])->save();

        $this->actingAs($intrus);

        $this->tenterAbonnement()->assertStatus(403);
    }

    /** ATTAQUE (c bis) — la branche `dispatcher`, celle qui ne désignait personne de légitime. */
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

    /** Le pendant positif : un administrateur de plateforme continue d'écouter. */
    public function test_un_administrateur_de_plateforme_ecoute_toujours_la_presence(): void
    {
        // La fabrique `admin()` pose `platform_role = admin` ; `role` n'entre pour rien dans la
        // décision désormais.
        $this->actingAs(User::factory()->admin()->create(['is_active' => true]));

        $this->tenterAbonnement()->assertStatus(200);
    }

    /** Un administrateur désactivé n'écoute plus rien : `canAccessAdminModule()` vérifie `is_active`, ce que l'ancienne lecture de `users.role` ne regardait pas. */
    public function test_un_administrateur_desactive_n_ecoute_plus_la_presence(): void
    {
        $suspendu = User::factory()->admin()->create(['is_active' => false]);

        $this->actingAs($suspendu);

        $this->tenterAbonnement()->assertStatus(403);
    }
}
