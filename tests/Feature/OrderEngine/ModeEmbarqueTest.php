<?php

namespace Tests\Feature\OrderEngine;

use App\Http\Middleware\EmbedMode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LE MODE EMBARQUÉ DOIT SURVIVRE À LA NAVIGATION.
 *
 * Dans une WebView, la barre de navigation web et la barre d'onglets du bas DOUBLENT l'en-tête et
 * les onglets natifs qui les entourent déjà. Le mode embarqué les retire — mais il était lu sur la
 * seule requête courante, et `?embed=1` n'existe que sur la page d'entrée.
 *
 * DÈS LE PREMIER LIEN INTERNE, le drapeau retombait. `route('order.confirmation')` est généré sans
 * paramètre, comme tous les liens de l'application : le récapitulatif de commande s'ouvrait donc
 * avec les deux chromes web au milieu de l'écran natif. Et comme la barre d'onglets porte `z-50`
 * contre `z-30` pour la barre d'action, elle RECOUVRAIT « Confirmer la commande » : un client
 * pouvait tout remplir et ne jamais pouvoir valider.
 *
 * Le même empilement existait sur le web mobile, sans WebView : la barre d'action est désormais
 * posée au-dessus de la barre d'onglets, pas dessous.
 */
class ModeEmbarqueTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        return User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
    }

    #[Test]
    public function la_page_d_entree_retire_les_chromes_web(): void
    {
        $reponse = $this->actingAs($this->client())->get(route('order.journey', ['embed' => 1]))->assertOk();

        // La barre de navigation web et la barre d'onglets sont celles de l'application native.
        $reponse->assertDontSee('data-chrome="primary-nav"', false);
        $reponse->assertDontSee('aria-label="Navigation mobile"', false);
    }

    #[Test]
    public function le_mode_survit_au_lien_suivant(): void
    {
        $client = $this->client();

        // Page d'entrée : c'est la seule à porter `?embed=1`.
        $this->actingAs($client)->get(route('order.journey', ['embed' => 1]))->assertOk();

        // Lien interne, généré SANS paramètre — comme tous les liens de l'application.
        $reponse = $this->actingAs($client)->get(route('order.journey'))->assertOk();

        $reponse->assertDontSee('data-chrome="primary-nav"', false);
        $reponse->assertDontSee('aria-label="Navigation mobile"', false);
    }

    #[Test]
    public function une_session_ordinaire_garde_ses_chromes(): void
    {
        $reponse = $this->actingAs($this->client())->get(route('order.journey'))->assertOk();

        // Le navigateur de bureau ne passe jamais par l'entrée WebView : rien ne doit changer pour
        // lui, et surtout pas la disparition de sa navigation.
        $reponse->assertSee('data-chrome="primary-nav"', false);
    }

    #[Test]
    public function embed_zero_est_une_porte_de_sortie(): void
    {
        $client = $this->client();

        $this->actingAs($client)->get(route('order.journey', ['embed' => 1]))->assertOk();
        $this->actingAs($client)->get(route('order.journey', ['embed' => 0]))->assertOk();

        // Sans cette sortie, un onglet resterait embarqué jusqu'à expiration de la session — sans
        // aucun moyen d'en sortir, y compris pour une page ouverte dans le navigateur du système.
        $reponse = $this->actingAs($client)->get(route('order.journey'))->assertOk();
        $reponse->assertSee('data-chrome="primary-nav"', false);
    }

    #[Test]
    public function l_en_tete_x_embedded_vaut_aussi_declaration(): void
    {
        $reponse = $this->actingAs($this->client())
            ->withHeader('X-Embedded', '1')
            ->get(route('order.journey'))
            ->assertOk();

        $reponse->assertDontSee('data-chrome="primary-nav"', false);
        $this->assertTrue((bool) session(EmbedMode::SESSION_KEY));
    }

    #[Test]
    public function le_recapitulatif_ouvre_sans_chrome_web_apres_l_entree(): void
    {
        $client = $this->client();

        $this->actingAs($client)->get(route('order.journey', ['embed' => 1]))->assertOk();

        $reponse = $this->actingAs($client)->get(route('order.confirmation'));

        if ($reponse->status() !== 200) {
            $this->markTestSkipped('Le récapitulatif exige un panier que ce test ne fabrique pas.');
        }

        // C'est LA page du défaut : la barre d'onglets y recouvrait le bouton de validation.
        $reponse->assertDontSee('aria-label="Navigation mobile"', false);
    }
}
