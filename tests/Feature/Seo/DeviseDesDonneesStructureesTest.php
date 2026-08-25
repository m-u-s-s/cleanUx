<?php

namespace Tests\Feature\Seo;

use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LE PRIX QUE LES MOTEURS DE RECHERCHE LISENT.
 *
 * La page publique d'un metier portait `"priceCurrency": "EUR"` ecrit DEUX FOIS dans ses
 * donnees structurees, et « €/heure » dans sa reponse de FAQ. Cette page est publique : ce
 * balisage part chez Google. Un metier annonce en euros sur un marche qui facture en
 * dirhams n'est pas une coquetterie — c'est un prix affiche que personne ne paiera, et un
 * visiteur qui arrive avec ce chiffre en tete.
 *
 * Le metier n'appartient a aucune zone : la reponse est celle du MARCHE, pas d'un lecteur
 * qui n'est meme pas connecte.
 */
class DeviseDesDonneesStructureesTest extends TestCase
{
    use RefreshDatabase;

    private function metier(): Trade
    {
        return Trade::factory()->create([
            'is_active' => true,
            'default_hourly_rate' => 45,
        ]);
    }

    public function test_les_donnees_structurees_suivent_la_devise_du_marche(): void
    {
        config(['fx.base_currency' => 'MAD']);

        $rendu = $this->get('/services/'.$this->metier()->slug)->assertOk()->getContent();

        $this->assertStringContainsString('"priceCurrency": "MAD"', $rendu);
        $this->assertStringNotContainsString('"priceCurrency": "EUR"', $rendu);
    }

    /**
     * TEMOIN POSITIF — le marche belge rend bien « EUR ».
     *
     * Sans lui, une vue qui rendrait toujours « MAD » passerait le test precedent en
     * mesurant une constante deguisee.
     */
    public function test_temoin_le_marche_belge_rend_l_euro(): void
    {
        config(['fx.base_currency' => 'EUR']);

        $this->get('/services/'.$this->metier()->slug)
            ->assertOk()
            ->assertSee('"priceCurrency": "EUR"', false);
    }

    /** La reponse de FAQ porte le SYMBOLE de la meme devise, pas un euro fige. */
    public function test_la_faq_porte_le_symbole_de_la_devise_du_marche(): void
    {
        config(['fx.base_currency' => 'MAD']);

        $rendu = $this->get('/services/'.$this->metier()->slug)->assertOk()->getContent();

        $this->assertStringContainsString('A partir de 45', $rendu);
        $this->assertStringNotContainsString('45'."\u{20ac}".'/heure', $rendu);
    }

    /**
     * TEMOIN — la page reste servie quand le metier n'a PAS de tarif.
     *
     * L'autre branche du balisage portait elle aussi un « EUR » fige : la corriger sans
     * l'exercer laisserait passer une vue qui ne compile plus.
     */
    public function test_temoin_un_metier_sans_tarif_rend_quand_meme(): void
    {
        config(['fx.base_currency' => 'MAD']);

        $sansTarif = Trade::factory()->create(['is_active' => true, 'default_hourly_rate' => null]);

        $this->get('/services/'.$sansTarif->slug)
            ->assertOk()
            ->assertSee('"priceCurrency": "MAD"', false);
    }
}
