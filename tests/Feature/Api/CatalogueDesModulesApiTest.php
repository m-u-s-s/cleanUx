<?php

namespace Tests\Feature\Api;

use App\Enums\ProviderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LE MOBILE LIT LE MÊME CATALOGUE QUE LE WEB.
 *
 * POURQUOI PAR L'API PLUTÔT QU'EN DUR. Le mobile a déjà `config/parity.php`, une seconde liste de
 * modules qu'il faut tenir à jour à côté de `config/modules.php`. En écrire une troisième, en
 * TypeScript, serait la troisième occasion d'oublier une entrée — et ce dépôt a déjà payé le prix
 * d'une table dupliquée : deux copies du même hook, donc deux fois le même défaut.
 *
 * Le contexte n'est PAS un paramètre de requête : il se déduit du rôle canonique du porteur du
 * jeton. Le laisser passer par la requête permettrait à un client de lire la liste des 90 modules
 * d'administration — les cases ne s'ouvriraient pas, mais la liste renseigne sur la plateforme.
 */
class CatalogueDesModulesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_client_recoit_ses_modules_groupes_par_categorie(): void
    {
        $client = User::factory()->client()->create();

        $reponse = $this->actingAs($client, 'sanctum')->getJson('/api/modules');

        $reponse->assertOk();
        $reponse->assertJsonPath('context', 'client');
        $reponse->assertJsonStructure([
            'context',
            'groups' => [['category', 'label', 'modules' => [['key', 'label', 'icon', 'path']]]],
        ]);
    }

    public function test_le_client_ne_recoit_pas_les_modules_d_administration(): void
    {
        $client = User::factory()->client()->create();

        $reponse = $this->actingAs($client, 'sanctum')->getJson('/api/modules');

        $this->assertStringNotContainsString('Feature flags', $reponse->getContent() ?: '');
    }

    public function test_l_admin_recoit_les_siens(): void
    {
        /*
         * LA CAPACITE EST ACCORDEE, ET C'EST LE SUJET.
         *
         * « Feature flags » declare desormais `manage-modules`, comme les quatre-vingt-trois autres
         * modules d'administration. `User::factory()->admin()` ne porte aucune capacite : le
         * catalogue la lui cachait donc, a juste titre.
         *
         * Ce test verifie que l'API rend bien SES modules a un administrateur -- il faut donc lui
         * en donner au moins un. Le suivant, en sens inverse, prouve qu'un client n'en recoit
         * aucun.
         */
        $admin = User::factory()->admin()->create();
        $admin->forceFill(['permissions' => ['manage-modules']])->save();

        $reponse = $this->actingAs($admin->refresh(), 'sanctum')->getJson('/api/modules');

        $reponse->assertJsonPath('context', 'admin');
        $this->assertStringContainsString('Feature flags', $reponse->getContent() ?: '');
    }

    /**
     * TEMOIN EN SENS INVERSE — sans la capacite, le module disparait de l'API aussi.
     *
     * L'API et la navigation web lisent le meme catalogue ; si l'une filtrait et pas l'autre, le
     * client mobile afficherait des cases qui repondent 403. Ce test fixe le fait que les deux
     * disent la meme chose.
     */
    public function test_un_admin_sans_la_capacite_ne_recoit_pas_le_module(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->forceFill(['permissions' => ['manage-users']])->save();

        $reponse = $this->actingAs($admin->refresh(), 'sanctum')->getJson('/api/modules');

        $reponse->assertJsonPath('context', 'admin');
        $this->assertStringNotContainsString('Feature flags', $reponse->getContent() ?: '');
    }

    public function test_le_contexte_ne_se_dicte_pas_depuis_la_requete(): void
    {
        // Un client qui réclame le contexte admin reçoit le sien : le rôle décide, pas l'appelant.
        $client = User::factory()->client()->create();

        $reponse = $this->actingAs($client, 'sanctum')->getJson('/api/modules?context=admin');

        $reponse->assertJsonPath('context', 'client');
    }

    public function test_chaque_module_porte_un_chemin_ouvrable(): void
    {
        /*
         * Le mobile ouvre ces modules dans l'hôte WebView : sans chemin, la case serait un libellé
         * qui ne mène nulle part — exactement le défaut que la page Modules du web a corrigé.
         */
        $employe = User::factory()->employe()->create();
        $employe->providerProfile()->create(['provider_type' => ProviderType::INDEPENDENT->value]);

        $reponse = $this->actingAs($employe->fresh(), 'sanctum')->getJson('/api/modules');

        $reponse->assertOk();
        foreach ($reponse->json('groups') as $groupe) {
            foreach ($groupe['modules'] as $module) {
                $this->assertNotEmpty($module['path'], $module['key']);
                $this->assertStringStartsWith('/', $module['path'], $module['key']);
            }
        }
    }

    public function test_les_modules_transversaux_sont_servis_a_tous(): void
    {
        $client = User::factory()->client()->create();

        $contenu = $this->actingAs($client, 'sanctum')->getJson('/api/modules')->getContent() ?: '';

        foreach (['Mon compte', 'Notifications', 'Aide'] as $transversal) {
            $this->assertStringContainsString($transversal, $contenu);
        }
    }

    public function test_un_anonyme_est_refuse(): void
    {
        $this->getJson('/api/modules')->assertUnauthorized();
    }
}
