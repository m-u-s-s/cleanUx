<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\OrderDraft;
use App\Models\Trade;
use App\Services\OrderEngine\OrderDraftManager;
use App\Support\Domain\OrderMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * « MODIFIER », AU RÉCAPITULATIF, PERDAIT TOUTE LA COMMANDE.

 *

 * Le lien renvoie au parcours SANS RIEN dans l'URL. `mount()` y relisait l'adresse et le

 * bénéficiaire du panier, mais ni le métier, ni le domaine, ni le mode : un client venu

 * corriger son adresse repartait de « De quoi avez-vous besoin ? », mode remis à zéro.

 *

 * Reproduit sur l'émulateur le 2026-09-05 : mode « Prendre rendez-vous », domaine Nettoyage,

 * métier « Nettoyage à domicile », devis 45 € — après « Modifier », plus aucun choix retenu.
 */
class ReprendreSaCommandeTest extends TestCase
{
    use RefreshDatabase;

    private function metier(): Trade
    {

        return Trade::factory()->create([

            'slug' => 'nettoyage-domicile',

            'code' => 'NET_DOM',

            'is_active' => true,

        ]);

    }

    /** Le panier se fabrique par le VRAI gestionnaire : une ligne inventee ne prouverait rien. */
    private function panier(?Trade $metier, string $mode, string $jeton = 'jeton-de-reprise'): OrderDraft
    {

        $manager = app(OrderDraftManager::class);

        $draft = $manager->resumeOrCreate($jeton, null, $mode);

        if ($metier) {

            $manager->itemFor($draft, $metier);

        }

        return $draft->refresh();

    }

    public function test_revenir_sans_url_retrouve_le_metier_et_le_mode(): void
    {

        $metier = $this->metier();

        $this->panier($metier, OrderMode::SCHEDULED);

        session(['order_draft_token' => 'jeton-de-reprise']);

        $parcours = Livewire::test(OrderJourney::class);

        $parcours->assertSet('tradeId', $metier->id);

        $parcours->assertSet('sectorId', $metier->sector_id);

        // `intendedMode` est une INTENTION que `selectTrade` consomme : c'est `mode` qui

        // porte le resultat, et c'est lui que l'ecran suivant lira.

        $parcours->assertSet('mode', OrderMode::SCHEDULED);

    }

    public function test_temoin_l_url_garde_la_main_sur_le_panier(): void
    {

        // Sans ce contrôle, la reprise pourrait écraser un choix explicite et le test

        // ci-dessus passerait au vert en mesurant une régression.

        $ancien = $this->metier();

        $voulu = Trade::factory()->create(['slug' => 'lavage-vitres', 'code' => 'LAV_VIT', 'is_active' => true]);

        $this->panier($ancien, OrderMode::SCHEDULED);

        session(['order_draft_token' => 'jeton-de-reprise']);

        Livewire::test(OrderJourney::class, ['trade' => $voulu->slug])

            ->assertSet('tradeId', $voulu->id);

    }

    public function test_temoin_un_panier_sans_article_ne_choisit_rien(): void
    {

        // Un panier neuf ne doit rien présélectionner : le client verrait un métier

        // qu'il n'a jamais demandé.

        $this->panier(null, OrderMode::SCHEDULED, 'jeton-vide');

        session(['order_draft_token' => 'jeton-vide']);

        Livewire::test(OrderJourney::class)->assertSet('tradeId', null);

    }

}
