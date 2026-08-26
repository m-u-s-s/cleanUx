<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `verified` GARDAIT TOUS LES GROUPES WEB ET AUCUNE ROUTE D'API.
 *
 * L'asymétrie était assumée tant qu'imposer la règle aurait déconnecté le parc installé. Il n'y
 * a pas de parc : `deploy.yml` n'a jamais réussi une seule fois sur 199 exécutions. Et l'impasse
 * technique est levée depuis que l'application peut se faire renvoyer l'e-mail.
 *
 * ON EXERCE AVEC UN VRAI JETON PORTEUR, pas `Sanctum::actingAs` — celui-ci fabrique
 * l'authentification et saute ce qui se joue avant les middlewares.
 */
class UneAdresseNonConfirmeeNAgitPlusParLApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function pointsGardes(): array
    {
        return [
            'profil partagé' => ['/api/profile'],
            'notifications' => ['/api/notifications'],
            'modules du rôle' => ['/api/modules'],
            'réservations client' => ['/api/client/bookings'],
        ];
    }

    #[DataProvider('pointsGardes')]
    public function test_une_adresse_non_confirmee_est_refusee(string $uri): void
    {
        $this->avecJeton(User::factory()->client()->unverified()->create())
            ->getJson($uri)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'email_non_verifie');
    }

    /**
     * LE TÉMOIN. Sans lui, le refus ci-dessus passerait au vert le jour où ces routes tombent :
     * on mesurerait la panne au lieu de la garde.
     */
    #[DataProvider('pointsGardes')]
    public function test_temoin_le_meme_point_repond_a_une_adresse_confirmee(string $uri): void
    {
        $this->avecJeton(User::factory()->client()->create())
            ->getJson($uri)
            ->assertOk();
    }

    /**
     * LES EXEMPTIONS DOIVENT VRAIMENT S'OUVRIR. Un mur sans porte est un compte perdu : il faut
     * pouvoir lire son état, se faire renvoyer la preuve, et partir.
     */
    public function test_une_adresse_non_confirmee_garde_de_quoi_s_en_sortir(): void
    {
        $user = User::factory()->client()->unverified()->create();

        $this->avecJeton($user)->getJson('/api/auth/me')->assertOk();
        $this->avecJeton($user)->postJson('/api/auth/email/verification-notification')->assertOk();
        $this->avecJeton($user)->postJson('/api/auth/logout')->assertOk();
    }

    /** LE WEB NE DOIT PAS AVOIR BOUGÉ : l'alias `verified` sert les deux surfaces. */
    public function test_le_web_redirige_toujours_vers_l_invite_de_confirmation(): void
    {
        $this->actingAs(User::factory()->client()->unverified()->create())
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    /** Et son témoin : une adresse confirmée n'est pas renvoyée sur l'invite. */
    public function test_temoin_le_web_laisse_passer_une_adresse_confirmee(): void
    {
        $reponse = $this->actingAs(User::factory()->client()->create())->get('/dashboard');

        $this->assertNotSame(route('verification.notice'), $reponse->headers->get('Location'));
    }

    /** L'INVARIANT : les quatre cas ne couvrent que quatre routes, celui-ci les couvre toutes. */
    public function test_chaque_route_api_authentifiee_exige_une_adresse_confirmee(): void
    {
        /*
         * Les sept exemptions. Six ouvrent la seule issue possible — lire son état, se faire
         * renvoyer la preuve, ouvrir la page de confirmation, renouveler son jeton, partir. La
         * septième est le droit d'accès de l'article 15, qui ne dépend pas de l'état du compte.
         */
        $exemptees = [
            'api/auth/me',
            'api/auth/refresh',
            'api/auth/email/verification-notification',
            'api/auth/webview-ticket',
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

            if (in_array($uri, $exemptees, true) || in_array('verified', $middlewares, true)) {
                continue;
            }

            $nues[$uri] = true;
        }

        $this->assertGreaterThan(400, $vues,
            'Presque aucune route authentifiée relevée : ce test ne mesurerait plus rien.');

        $this->assertSame([], array_keys($nues),
            'Ces routes d’API agissent encore sans adresse e-mail confirmée.');
    }

    private function avecJeton(User $user): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('telephone')->plainTextToken);
    }
}
