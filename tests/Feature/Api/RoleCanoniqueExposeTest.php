<?php

namespace Tests\Feature\Api;

use App\Enums\ProviderType;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'API DIT LE RÔLE, PLUS UNE COLLECTION DE DRAPEAUX À RECOMBINER.
 *
 * `/auth/me` a gagné `is_admin`, puis `is_provider`, puis `is_entreprise`, puis
 * `organization_type`, puis `can_manage_company` — chacun ajouté APRÈS avoir manqué, et chacun
 * documenté par le défaut qu'il a causé : un administrateur redevenu compte ordinaire à la reprise
 * de session, un compte société redevenu particulier, un espace prestataire inatteignable parce
 * que deux drapeaux se contredisaient.
 *
 * Le rôle canonique les subsume : une chaîne, résolue au même endroit que côté web. Les drapeaux
 * restent — les retirer casserait les applications installées — mais l'aiguillage mobile n'a plus
 * à les recombiner lui-même.
 */
class RoleCanoniqueExposeTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_me_annonce_le_role_canonique(): void
    {
        $user = User::factory()->create(['platform_role' => 'super_admin']);

        $reponse = $this->actingAs($user, 'sanctum')->getJson('/api/auth/me');

        $reponse->assertOk();
        $reponse->assertJsonPath('role', Role::SUPER_ADMIN->value);
        $reponse->assertJsonPath('user.role', Role::SUPER_ADMIN->value);
    }

    public function test_le_super_admin_se_distingue_de_l_admin(): void
    {
        /*
         * LE DÉFAUT QUE CE TEST EMPÊCHE.
         *
         * `is_admin` est vrai pour les deux. Une application qui n'aurait que ce drapeau ouvrirait
         * le même espace aux deux rôles — et le sixième n'existerait pas sur mobile.
         */
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
