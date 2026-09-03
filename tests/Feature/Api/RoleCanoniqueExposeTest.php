<?php

namespace Tests\Feature\Api;

use App\Enums\ProviderType;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** L'API DIT LE RÔLE, PLUS UNE COLLECTION DE DRAPEAUX À RECOMBINER. */
class RoleCanoniqueExposeTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_me_annonce_le_role_canonique(): void
    {
        $user = $this->prendreLeSiege();

        $reponse = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');

        $reponse->assertOk();
        $reponse->assertJsonPath('role', Role::SUPER_ADMIN->value);
        $reponse->assertJsonPath('user.role', Role::SUPER_ADMIN->value);
    }

    public function test_le_super_admin_se_distingue_de_l_admin(): void
    {
        // LE DÉFAUT QUE CE TEST EMPÊCHE. `is_admin` est vrai pour les deux.
        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin, 'sanctum')->getJson('/api/auth/me');

        $reponse->assertJsonPath('role', Role::ADMIN->value);
        $reponse->assertJsonPath('is_admin', true);
    }

    public function test_le_prestataire_de_societe_se_distingue_de_l_independant(): void
    {
        $user = User::factory()->employe()->create();
        $user->providerProfile()->create(['provider_type' => ProviderType::COMPANY_WORKER->value]);

        $reponse = $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/auth/me');

        $reponse->assertJsonPath('role', Role::PROVIDER_SOCIETE->value);
    }

    public function test_la_connexion_annonce_le_meme_role_que_la_reprise_de_session(): void
    {
        // La reprise de session doit dire la même chose que la connexion : c'est la divergence
        // entre les deux qui a produit tous les drapeaux ajoutés après coup.
        $user = User::factory()->client()->create([
            'password' => bcrypt('mot-de-passe-de-test'),
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);

        $connexion = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'mot-de-passe-de-test',
        ]);

        $connexion->assertOk();
        $connexion->assertJsonPath('user.role', Role::CLIENT_INDIVIDUELLE->value);

        $reprise = $this->actingAs($user->fresh(), 'sanctum')->getJson('/api/auth/me');

        $this->assertSame(
            $connexion->json('user.role'),
            $reprise->json('role'),
            'La connexion et la reprise de session doivent annoncer le même rôle.'
        );
    }
}
