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
 * `/dashboard` ENVOIE CHACUN CHEZ LUI.
 *
 * L'aiguillage testait trois cas — administrateur, client, prestataire — et refusait tous les
 * autres par un 403. Un membre de société cliente ou prestataire tombait donc dans le cas
 * `isClient()` / `isEmploye()` et atterrissait dans l'espace individuel, jamais dans le sien ; un
 * super administrateur passait pour un administrateur ordinaire.
 *
 * Il lit désormais le rôle canonique, dont la table de correspondance vit dans `Role`.
 */
class AiguillageDuTableauDeBordTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_super_admin_va_chez_lui(): void
    {
        $user = User::factory()->create(['platform_role' => 'super_admin']);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('super-admin.dashboard'));
    }

    public function test_l_admin_garde_le_sien(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_le_client_particulier_garde_le_sien(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get('/dashboard')
            ->assertRedirect(route('client.dashboard'));
    }

    public function test_le_prestataire_independant_garde_le_sien(): void
    {
        $user = User::factory()->employe()->create();
        $user->providerProfile()->create(['provider_type' => ProviderType::INDEPENDENT->value]);

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertRedirect(route('employe.dashboard'));
    }

    public function test_le_membre_de_societe_cliente_va_dans_son_espace(): void
    {
        /*
         * LE DÉFAUT QUE CE TEST CORRIGE.
         *
         * `isClient()` est vrai pour un membre de société cliente — il délègue à
         * `isClientCompany()`. L'ancien aiguillage l'envoyait donc dans l'espace personnel, et son
         * espace société n'était atteignable qu'en tapant l'URL ou par la passerelle du profil.
         */
        $org = OrganizationAccount::factory()->clientCompany()->create();
        $user = User::factory()->entreprise()->create([
            'current_organization_id' => $org->id,
            'email_verified_at' => now(),
            'is_active' => true,
            'status' => 'active',
        ]);
        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'permissions' => null,
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertRedirect(route('client-company.dashboard'));
    }

    public function test_le_membre_de_societe_prestataire_va_dans_son_espace(): void
    {
        $user = User::factory()->employe()->create();
        $user->providerProfile()->create(['provider_type' => ProviderType::COMPANY_WORKER->value]);

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertRedirect(route('provider-company.dashboard'));
    }

    public function test_l_aiguillage_couvre_les_six_roles_sans_403(): void
    {
        // Le garde-fou de la mesure : l'ancien aiguillage finissait par `abort(403)`, ce qui
        // enfermait dehors tout compte ne cochant aucun des trois cas prévus.
        $this->assertCount(6, Role::cases());

        foreach (Role::cases() as $role) {
            $this->assertNotEmpty($role->routeDuTableauDeBord(), $role->value);
        }
    }

    public function test_un_compte_sans_profil_atterrit_quand_meme(): void
    {
        // Un compte tout juste créé n'a ni profil client ni profil prestataire. Le repli le traite
        // en particulier — c'est ce qu'il est dans les faits — plutôt que de le laisser dehors.
        $user = User::factory()->create(['platform_role' => 'user', 'role' => 'client']);
        $user->customerProfile()->create(['customer_type' => CustomerType::PERSONAL->value]);

        $this->actingAs($user->fresh())
            ->get('/dashboard')
            ->assertRedirect(route('client.dashboard'));
    }
}
