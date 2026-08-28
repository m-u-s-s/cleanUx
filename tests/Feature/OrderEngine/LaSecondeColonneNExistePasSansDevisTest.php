<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\Trade;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * La grille reservait 340 px pour le panneau d'estimation meme quand il n'existait pas :
 * le contenu occupait 876 px sur 1280 et paraissait colle a gauche.
 */
class LaSecondeColonneNExistePasSansDevisTest extends TestCase
{
    use RefreshDatabase;

    private const GRILLE = 'lg:grid-cols-[1fr_340px]';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    public function test_sans_devis_la_page_n_a_qu_une_colonne(): void
    {
        $rendu = Livewire::test(OrderJourney::class)->html();

        $this->assertStringNotContainsString(self::GRILLE, $rendu,
            'Une seconde colonne est reservee alors qu\'aucun panneau ne la remplit.');
        $this->assertStringContainsString('mx-auto', $rendu);
    }

    /**
     * TEMOIN — des qu'un metier est choisi, le devis existe et la seconde colonne revient.
     * Sans ce controle, le test ci-dessus resterait vert si la grille avait simplement disparu.
     */
    public function test_temoin_avec_un_devis_la_seconde_colonne_revient(): void
    {
        $composant = Livewire::test(OrderJourney::class)
            ->call('selectTrade', Trade::where('slug', 'peinture')->firstOrFail()->id);

        $this->assertNotNull($composant->instance()->quote(),
            'Le metier choisi ne produit pas de devis : le temoin ne prouverait rien.');

        $this->assertStringContainsString(self::GRILLE, $composant->html());
    }
}
