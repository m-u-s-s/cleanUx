<?php

namespace Tests\Feature\Pages;

use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** LES PAGES PUBLIQUES D'ACQUISITION DOIVENT S'AFFICHER (B0). */
class PagesPubliquesRepondentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function la_page_des_tarifs_s_affiche(): void
    {
        $this->get('/pricing')->assertOk();
    }

    #[Test]
    public function le_catalogue_des_services_s_affiche(): void
    {
        $this->get('/services')->assertOk();
    }

    #[Test]
    public function la_page_d_un_metier_s_affiche(): void
    {
        $metier = Trade::factory()->create(['is_active' => true]);

        $this->get('/services/'.$metier->slug)->assertOk();
    }

    /** LE JSON-LD DOIT RESTER VALIDE APRÈS ÉCHAPPEMENT. */
    #[Test]
    public function les_donnees_structurees_sortent_avec_un_seul_arobase(): void
    {
        $contenu = $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringContainsString('"@context"', (string) $contenu);
        $this->assertStringNotContainsString('"@@context"', (string) $contenu);
    }
}
