<?php

namespace Tests\Feature\WebView;

use App\Http\Middleware\EmbedMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UNE WEBVIEW RENVOYÉE SUR `/login` RESTE DEVANT UN FORMULAIRE QU'ELLE NE PEUT PAS REMPLIR.
 *
 * L'hôte embarqué sait refaire la passation avec son jeton Sanctum — `EmbeddedModuleScreen`
 * écoute `sessionExpired` et recharge une fois en silence. Encore faut-il que la page le lui
 * DISE : quand la session mourait en cours de parcours, l'application redirigeait vers la page
 * de connexion publique, et cette reprise ne partait jamais.
 *
 * Reproduit sur l'émulateur le 2026-09-06 : en plein « Nouvelle réservation », après le choix
 * du métier, la WebView affichait « Bon retour parmi nous » sous un en-tête natif inchangé.
 */
class SessionPerdueDansUneWebViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_invite_embarque_recoit_la_page_du_pont(): void
    {
        $this->withCookie(EmbedMode::COOKIE, '1')
            ->get('/dashboard')
            ->assertRedirect(route('webview.session-expired'));
    }

    public function test_temoin_un_invite_ordinaire_va_toujours_vers_la_connexion(): void
    {
        // Sans ce contrôle, on pourrait envoyer TOUT LE WEB sur une page de WebView.
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_la_page_du_pont_poste_le_message_attendu(): void
    {
        $this->get(route('webview.session-expired'))
            ->assertStatus(419)
            ->assertSee('sessionExpired', false)
            ->assertSee('ReactNativeWebView', false);
    }

    public function test_le_marqueur_survit_a_la_session(): void
    {
        // Le cookie se pose à l'entrée embarquée ; c'est lui qui parle quand la session n'est plus là.
        $this->get('/?embed=1')->assertCookie(EmbedMode::COOKIE, '1');
    }

    public function test_temoin_une_sortie_explicite_efface_le_marqueur(): void
    {
        // Sans effacement, un onglet resterait « embarqué » et n'atteindrait plus jamais /login.
        $this->withCookie(EmbedMode::COOKIE, '1')
            ->get('/?embed=0')
            ->assertCookieExpired(EmbedMode::COOKIE);
    }
}
