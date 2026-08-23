<?php

namespace Tests\Feature\Api;

use App\Enums\ProviderType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** LE MOBILE LIT LE MÊME CATALOGUE QUE LE WEB. POURQUOI PAR L'API PLUTÔT QU'EN DUR. */
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
        // LA CAPACITE EST ACCORDEE, ET C'EST LE SUJET.
        $admin = User::factory()->admin()->create();
        $admin->forceFill(['permissions' => ['manage-modules']])->save();

        $reponse = $this->actingAs($admin->refresh(), 'sanctum')->getJson('/api/modules');

        $reponse->assertJsonPath('context', 'admin');
        $this->assertStringContainsString('Feature flags', $reponse->getContent() ?: '');
    }

    /** TEMOIN EN SENS INVERSE — sans la capacite, le module disparait de l'API aussi. */
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
        // Le mobile ouvre ces modules dans l'hôte WebView : sans chemin, la case serait un libellé qui ne mène nulle part — exactement le défaut que la page Modules du web a corrigé.
        $employe = User::factory()->employe()->create();
        $employe->providerProfile()->create(['provider_type' => ProviderType::INDEPENDENT->value]);

        $reponse = $this->actingAs($employe->fresh(), 'sanctum')->getJson('/api/modules');

        $reponse->assertOk();
        // Tous les chemins fautifs d'un coup : un catalogue mal forme en compte souvent plusieurs,
        // et une assertion par tour n'en nommerait qu'un.
        $fautifs = [];

        foreach ($reponse->json('groups') as $groupe) {
            foreach ($groupe['modules'] as $module) {
                $chemin = $module['path'] ?? '';

                if ($chemin === '' || $chemin === null) {
                    $fautifs[] = $module['key'].' : chemin vide';
                } elseif (! str_starts_with($chemin, '/')) {
                    $fautifs[] = $module['key'].' : chemin ['.$chemin.'] ne commence pas par /';
                }
            }
        }

        $this->assertSame([], $fautifs, 'Ces modules exposent un chemin inexploitable.');
    }

    public function test_les_modules_transversaux_sont_servis_a_tous(): void
    {
        $client = User::factory()->client()->create();

        $contenu = $this->actingAs($client, 'sanctum')->getJson('/api/modules')->getContent() ?: '';

        $absents = array_values(array_filter(
            ['Mon compte', 'Notifications', 'Aide'],
            fn (string $t) => ! str_contains($contenu, $t),
        ));

        $this->assertSame([], $absents, 'Ces modules transversaux ne sont pas servis.');
    }

    public function test_un_anonyme_est_refuse(): void
    {
        $this->getJson('/api/modules')->assertUnauthorized();
    }
}
