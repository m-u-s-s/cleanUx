<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\Sector;
use App\Models\Trade;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Les finitions du parcours que la spécification demande et que le module n'avait pas. */
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

    /** L'image de couverture est rendue quand elle existe — et PARESSEUSEMENT. */
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

    /** §6.1 — Le carrousel se tire à la SOURIS sur desktop. */
    public function test_the_carousel_can_be_dragged_with_the_mouse(): void
    {
        $html = Livewire::test(OrderJourney::class)->html();

        $this->assertStringContainsString('pointerdown', $html);
    }

    /** §6.3 — Une question conditionnelle apparaît avec une transition de HAUTEUR. */
    public function test_a_conditional_question_appears_with_a_height_transition(): void
    {
        $html = Livewire::test(OrderJourney::class)
            ->call('selectTrade', Trade::where('slug', 'peinture')->firstOrFail()->id)
            ->html();

        $this->assertStringContainsString('x-collapse', $html);
    }

    /** §10 — La perte de connexion est DITE, et ce qui a été répondu est déjà sauvé. */
    public function test_a_lost_connection_is_announced(): void
    {
        $html = Livewire::test(OrderJourney::class)
            ->call('selectTrade', Trade::where('slug', 'peinture')->firstOrFail()->id)
            ->html();

        $this->assertStringContainsString('navigator.onLine', $html);
        $this->assertStringContainsString('Vos réponses sont enregistrées', $html);
    }
}
