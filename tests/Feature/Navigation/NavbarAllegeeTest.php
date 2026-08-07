<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LA NAVBAR PORTE LES LIENS CHAUDS, PAS LE CATALOGUE ENTIER.
 *
 * Elle déversait 126 liens répartis en 22 groupes dans un menu déroulant « Toutes les pages ».
 * Le reste vit désormais dans la page Modules, où il est rangé par fonction.
 */
class NavbarAllegeeTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_navbar_admin_ne_deverse_plus_ses_quatre_vingts_liens(): void
    {
        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin)->get(route('admin.dashboard'));

        $reponse->assertOk();
        // « Feature flags » vivait dans le menu déroulant de la navbar ; il appartient désormais
        // à la page Modules.
        $reponse->assertDontSee('Feature flags');
        $reponse->assertDontSee('Toutes les pages');
    }

    public function test_la_navbar_mene_a_la_page_modules(): void
    {
        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin)->get(route('admin.dashboard'));

        // Sans ce lien, les 78 modules non-principaux deviendraient injoignables d'un coup.
        $reponse->assertSee(route('admin.modules.directory'), false);
    }

    public function test_les_liens_chauds_du_role_restent_dans_la_navbar(): void
    {
        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin)->get(route('admin.dashboard'));

        $reponse->assertSee(route('admin.planning'), false);
        $reponse->assertSee(route('admin.missions'), false);
    }

    public function test_le_client_garde_ses_cinq_liens_et_sa_porte_modules(): void
    {
        $client = User::factory()->client()->create();

        $reponse = $this->actingAs($client)->get(route('client.dashboard'));

        $reponse->assertOk();
        $reponse->assertSee(route('client.rendezvous.index'), false);
        $reponse->assertSee(route('client.modules'), false);
        $reponse->assertDontSee('Programme fidélité');
    }

    public function test_le_badge_du_logo_ne_porte_plus_les_initiales_de_l_ancienne_marque(): void
    {
        // « CU » = CleanUx. Le renommage global ne pouvait pas le voir : ce ne sont pas les
        // lettres « cleanux ».
        $nav = file_get_contents(resource_path('views/navigation-menu.blade.php'));

        $this->assertStringNotContainsString('>CU<', str_replace([' ', "\n"], '', $nav));
    }
}
