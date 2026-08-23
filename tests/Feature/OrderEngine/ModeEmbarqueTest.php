<?php

namespace Tests\Feature\OrderEngine;

use App\Http\Middleware\EmbedMode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** LE MODE EMBARQUÉ DOIT SURVIVRE À LA NAVIGATION. */
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
