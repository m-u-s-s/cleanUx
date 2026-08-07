<?php

namespace Tests\Feature\Navigation;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_client_voit_ses_modules_groupes_par_categorie(): void
    {
        $client = User::factory()->client()->create();

        $reponse = $this->actingAs($client)->get(route('client.modules'));

        $reponse->assertOk();
        $reponse->assertSee('Rendez-vous & planning');
        $reponse->assertSee('Mes rendez-vous');
        // La case doit MENER quelque part : un libellé seul ne prouve rien — c'est exactement
        // l'erreur des tests de joignabilité déjà présents dans ce dépôt.
        $reponse->assertSee(route('client.rendezvous.index'), false);
    }

    public function test_ne_montre_pas_a_un_client_les_modules_d_administration(): void
    {
        $client = User::factory()->client()->create();

        $reponse = $this->actingAs($client)->get(route('client.modules'));

        $reponse->assertDontSee('Feature flags');
    }

    public function test_ne_propose_pas_l_espace_entreprise_a_un_client_particulier(): void
    {
        /*
         * L'ancienne navbar conditionnait cette porte à `belongsToClientCompany()`. Une case sans
         * condition la montre à tout le monde : un particulier cliquerait vers un 403, et une
         * liste de modules qui ment sur ce qu'on peut ouvrir vaut moins que pas de liste.
         */
        $client = User::factory()->client()->create();

        $reponse = $this->actingAs($client)->get(route('client.modules'));

        $reponse->assertDontSee('Espace entreprise');
    }

    public function test_un_client_ne_peut_pas_ouvrir_la_page_modules_admin(): void
    {
        $client = User::factory()->client()->create();

        $this->actingAs($client)->get(route('admin.modules.directory'))->assertForbidden();
    }

    public function test_l_employe_voit_ses_propres_modules(): void
    {
        $employe = User::factory()->employe()->create();

        $reponse = $this->actingAs($employe)->get(route('employe.modules'));

        $reponse->assertOk();
        $reponse->assertSee('Mes missions');
        $reponse->assertSee(route('employe.missions'), false);
    }

    public function test_l_admin_voit_les_modules_de_pilotage(): void
    {
        $admin = User::factory()->admin()->create();

        $reponse = $this->actingAs($admin)->get(route('admin.modules.directory'));

        $reponse->assertOk();
        $reponse->assertSee('Feature flags');
        $reponse->assertSee(route('admin.feature-flags.manager'), false);
    }
}
