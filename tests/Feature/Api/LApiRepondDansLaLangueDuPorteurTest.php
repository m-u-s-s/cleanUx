<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * L'API répond dans la langue de celui qui appelle : celle qu'annonce la requête, sinon celle du
 * compte. Les messages de validation en dépendent, sur chaque route.
 */
class LApiRepondDansLaLangueDuPorteurTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTE = '/api/client/profile';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('i18n.locales', [
            'fr' => ['enabled' => true],
            'nl' => ['enabled' => true],
            'en' => ['enabled' => true],
        ]);
        Config::set('i18n.default', 'fr');
    }

    private function client(?string $locale): User
    {
        return User::factory()->client()->create([
            'locale' => $locale,
            'email_verified_at' => now(),
        ]);
    }

    /** Un corps invalide : le message de validation est la chaîne traduite qu'on mesure. */
    private function messageDeValidation(array $entetes = []): string
    {
        $reponse = $this->putJson(self::ROUTE, ['name' => ['pas une chaine']], $entetes);

        $reponse->assertStatus(422);

        return (string) $reponse->json('message');
    }

    public function test_la_langue_du_compte_est_respectee(): void
    {
        Sanctum::actingAs($this->client('nl'));

        $this->assertStringContainsString('naam', mb_strtolower($this->messageDeValidation()),
            'Le message n’est pas en néerlandais : la langue du compte est ignorée.');
    }

    /** LE TÉMOIN : le même appel, compte en français, reste en français. */
    public function test_temoin_un_compte_francais_reste_en_francais(): void
    {
        Sanctum::actingAs($this->client('fr'));

        $this->assertStringContainsString('nom', mb_strtolower($this->messageDeValidation()),
            'Le message n’est pas en français : la mesure ne compare plus rien.');
    }

    /**
     * La déclaration explicite l'emporte sur le compte — même priorité que sur le web, où
     * `?lang=` a toujours gagné. L'application peut donc imposer sa langue d'affichage.
     */
    public function test_la_langue_declaree_par_la_requete_prime_sur_le_compte(): void
    {
        Sanctum::actingAs($this->client('fr'));

        $reponse = $this->putJson(self::ROUTE.'?lang=nl', ['name' => ['pas une chaine']]);

        $reponse->assertStatus(422);

        $this->assertStringContainsString('naam', mb_strtolower((string) $reponse->json('message')));
    }

    /**
     * L'en-tête du navigateur ne sert QUE de repli, quand le compte ne porte pas de langue
     * utilisable. Le compte reste plus fort que lui : c'est l'ordre déjà en place sur le web.
     */
    public function test_l_entete_sert_de_repli_quand_le_compte_n_a_pas_de_langue_utilisable(): void
    {
        Sanctum::actingAs($this->client('pt'));

        $message = mb_strtolower($this->messageDeValidation(['Accept-Language' => 'nl-BE,nl;q=0.9']));

        $this->assertStringContainsString('naam', $message);
    }

    /** LE TÉMOIN : sans rien d'utilisable nulle part, la valeur par défaut s'applique. */
    public function test_temoin_sans_langue_utilisable_la_valeur_par_defaut_s_applique(): void
    {
        Sanctum::actingAs($this->client('pt'));

        $this->assertStringContainsString('nom', mb_strtolower($this->messageDeValidation()));
    }

    /**
     * LE POINT QUI AURAIT TOUT CASSÉ : le résolveur lisait la session sans garde. Une requête
     * d'API n'en a pas — elle serait morte en `RuntimeException` avant d'atteindre le contrôleur.
     */
    public function test_une_route_d_api_publique_repond_sans_session(): void
    {
        $this->getJson('/api/locales')->assertOk();
    }

    /** Et une route d'API authentifiée traverse bien le middleware sans session. */
    public function test_une_route_d_api_authentifiee_repond_sans_session(): void
    {
        Sanctum::actingAs($this->client('nl'));

        $this->getJson('/api/profile')->assertSuccessful();
    }

    /** Une réponse d'API ne pose pas de cookie de langue : elle est sans état. */
    public function test_une_reponse_d_api_ne_pose_pas_de_cookie_de_langue(): void
    {
        Sanctum::actingAs($this->client('nl'));

        $reponse = $this->getJson('/api/profile');

        $noms = array_map(fn ($c) => $c->getName(), $reponse->headers->getCookies());

        $this->assertNotContains('locale', $noms,
            'L’API pose un cookie de langue : la réponse n’est plus sans état.');
    }

    /** LE TÉMOIN DU COOKIE : le web, lui, continue de le poser. */
    public function test_temoin_le_web_pose_toujours_son_cookie_de_langue(): void
    {
        $reponse = $this->get('/?lang=nl');

        $noms = array_map(fn ($c) => $c->getName(), $reponse->headers->getCookies());

        $this->assertContains('locale', $noms,
            'Le web ne pose plus le cookie de langue : le changement a débordé sur le navigateur.');
    }
}
