<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * UN COMPTE DÉSACTIVÉ GARDAIT L'USAGE DE SON JETON.
 *
 * `active.account` gardait 253 routes web et AUCUNE route d'API : désactiver un compte depuis
 * l'admin fermait le site et laissait l'application mobile fonctionner. Le garde savait pourtant
 * déjà répondre en JSON — il n'avait simplement jamais été posé.
 *
 * TOUT TEST DE REFUS PORTE ICI SON TÉMOIN POSITIF : sans lui, un « ceci est refusé » passe au
 * vert le jour où la route tombe en panne, et mesure la panne au lieu de la garde.
 */
class UnCompteSuspenduNAgitPlusParLApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Un représentant par famille de routes authentifiées joignable par un client.
     *
     * @return array<string, array{string}>
     */
    public static function pointsDEntree(): array
    {
        return [
            'profil partagé' => ['/api/profile'],
            'notifications' => ['/api/notifications'],
            'modules du rôle' => ['/api/modules'],
            'réservations client' => ['/api/client/bookings'],
        ];
    }

    #[DataProvider('pointsDEntree')]
    public function test_un_compte_desactive_est_refuse(string $uri): void
    {
        Sanctum::actingAs(User::factory()->client()->create(['is_active' => false]));

        $this->getJson($uri)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'compte_inactif');
    }

    #[DataProvider('pointsDEntree')]
    public function test_temoin_le_meme_point_repond_a_un_compte_actif(string $uri): void
    {
        Sanctum::actingAs(User::factory()->client()->create());

        $this->getJson($uri)->assertOk();
    }

    public function test_un_statut_suspendu_ferme_aussi_la_porte(): void
    {
        Sanctum::actingAs(User::factory()->client()->create(['status' => 'suspended']));

        $this->getJson('/api/profile')
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'compte_inactif');
    }

    public function test_se_deconnecter_reste_permis_a_un_compte_suspendu(): void
    {
        Sanctum::actingAs(User::factory()->client()->create(['is_active' => false]));

        // Refuser la déconnexion laisserait l'application avec un jeton mort qu'elle ne peut rendre.
        $this->postJson('/api/auth/logout')->assertOk();
    }

    /**
     * L'INVARIANT. Les quatre cas ci-dessus ne couvrent que quatre routes ; celui-ci couvre les
     * 536. C'est lui qui attrape la route ajoutée demain sans son garde.
     */
    public function test_chaque_route_api_authentifiee_porte_le_garde(): void
    {
        /*
         * Les seules exemptions, et leur motif :
         * — se déconnecter doit rester possible (voir le cas ci-dessus) ;
         * — le droit d'accès de l'article 15 ne dépend pas de l'état du compte, et l'URL signée
         *   borne déjà cet accès-là.
         */
        $exemptees = [
            'api/auth/logout',
            'api/auth/logout-all',
            'api/client/gdpr/requests/{gdprRequest}/download',
        ];

        $nues = [];
        $vues = 0;

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/')) {
                continue;
            }

            $middlewares = array_diff($route->gatherMiddleware(), $route->excludedMiddleware());

            if (! in_array('auth:sanctum', $middlewares, true)) {
                continue;
            }

            $vues++;

            if (in_array($uri, $exemptees, true)) {
                continue;
            }

            if (! in_array('active.account', $middlewares, true)) {
                $nues[$uri] = true;
            }
        }

        $this->assertGreaterThan(400, $vues,
            'Presque aucune route authentifiée relevée : ce test ne mesurerait plus rien.');

        $this->assertSame([], array_keys($nues),
            'Ces routes d’API acceptent encore le jeton d’un compte désactivé.');
    }
}
