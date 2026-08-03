<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\Sector;
use App\Models\Trade;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Les finitions du parcours que la spécification demande et que le module n'avait pas.
 *
 * Aucune ne protège une règle — ce sont des commodités. Mais elles se tiennent : une carte sans
 * icône, un carrousel qu'on ne peut pas tirer à la souris et une question qui surgit d'un coup
 * donnent le même sentiment d'écran inachevé, sur l'écran le plus rentable du produit.
 */
class JourneyPolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    /** §6.1 — « Chaque carte : icône, nom du secteur, accroche courte, et un signal vivant. » */
    public function test_the_card_shows_the_sector_icon(): void
    {
        Livewire::test(OrderJourney::class)->assertSeeHtml('data-sector-icon');
    }

    /**
     * L'image de couverture est rendue quand elle existe — et PARESSEUSEMENT.
     *
     * `cover_image_path` existait en base sans jamais être affiché. Et cet écran est celui dont
     * dépend le LCP : charger d'emblée une image par secteur retarderait le premier rendu utile
     * pour des cartes qu'on ne verra peut-être jamais, le carrousel défilant à l'horizontale.
     */
    public function test_a_cover_image_is_rendered_lazily(): void
    {
        Sector::where('slug', 'nettoyage')->update(['cover_image_path' => 'sectors/nettoyage.jpg']);

        $html = Livewire::test(OrderJourney::class)->html();

        $this->assertStringContainsString('sectors/nettoyage.jpg', $html);
        $this->assertStringContainsString('loading="lazy"', $html);
    }

    /** Sans image, aucune balise vide : une image cassée est pire que pas d'image. */
    public function test_no_image_tag_without_a_cover(): void
    {
        $html = Livewire::test(OrderJourney::class)->html();

        $this->assertStringNotContainsString('<img src=""', $html);
        $this->assertStringNotContainsString('loading="lazy"', $html);
    }

    /**
     * §6.1 — Le carrousel se tire à la SOURIS sur desktop.
     *
     * Le doigt a l'inertie native, le clavier a les flèches et Home/End. La souris n'avait que les
     * deux boutons de pagination — sur une bande qui invite visiblement à être tirée.
     */
    public function test_the_carousel_can_be_dragged_with_the_mouse(): void
    {
        $html = Livewire::test(OrderJourney::class)->html();

        $this->assertStringContainsString('pointerdown', $html);
    }

    /**
     * §6.3 — Une question conditionnelle apparaît avec une transition de HAUTEUR.
     *
     * Elle surgissait d'un coup, décalant tout ce qui la suit. Sur un écran où le prix change en
     * même temps, ce saut fait perdre le fil de ce qui vient de se passer.
     */
    public function test_a_conditional_question_appears_with_a_height_transition(): void
    {
        $html = Livewire::test(OrderJourney::class)
            ->call('selectTrade', Trade::where('slug', 'peinture')->firstOrFail()->id)
            ->html();

        $this->assertStringContainsString('x-collapse', $html);
    }

    /**
     * §10 — La perte de connexion est DITE, et ce qui a été répondu est déjà sauvé.
     *
     * Chaque réponse part au serveur au fil de l'eau : une coupure ne perd rien de ce qui est
     * déjà passé. Encore faut-il le dire, sinon le client recommence — ou pire, abandonne en
     * croyant avoir tout perdu.
     */
    public function test_a_lost_connection_is_announced(): void
    {
        $html = Livewire::test(OrderJourney::class)
            ->call('selectTrade', Trade::where('slug', 'peinture')->firstOrFail()->id)
            ->html();

        $this->assertStringContainsString('navigator.onLine', $html);
        $this->assertStringContainsString('Vos réponses sont enregistrées', $html);
    }
}
