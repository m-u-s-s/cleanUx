<?php

namespace Tests\Feature\Pages;

use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LES PAGES PUBLIQUES D'ACQUISITION DOIVENT S'AFFICHER (B0).
 *
 * `/pricing` et `/services` rendaient 500. La cause : le JSON-LD des données structurées écrit
 * `"@context"`, et Blade lit `@context` comme une DIRECTIVE — non fermée, donc erreur de
 * compilation. `layouts/guest.blade.php` échappait déjà correctement en `@@context` ; les trois
 * pages ajoutées ensuite ne l'ont pas fait.
 *
 * CE SONT LES SEULES PAGES QUE VOIT UN VISITEUR NON INSCRIT — les tarifs et le catalogue de
 * services. Un lancement avec ces deux pages en erreur, c'est un lancement sans acquisition.
 *
 * ET RIEN NE LES COUVRAIT. Cent dix tests passaient sur ce périmètre sans jamais faire un GET sur
 * ces URL : `view:cache` ne détecte pas non plus cette erreur, puisqu'elle survient à la
 * compilation de la vue, au moment du rendu. Seule une requête réelle la voit.
 */
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

    /**
     * LE JSON-LD DOIT RESTER VALIDE APRÈS ÉCHAPPEMENT.
     *
     * `@@context` est la façon d'écrire `@context` en Blade : la page rendue doit donc contenir un
     * SEUL arobase. Un test qui se contenterait du 200 laisserait passer un double arobase, qui
     * rendrait les données structurées illisibles par les moteurs de recherche — l'objet même de
     * ces balises.
     */
    #[Test]
    public function les_donnees_structurees_sortent_avec_un_seul_arobase(): void
    {
        $contenu = $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringContainsString('"@context"', (string) $contenu);
        $this->assertStringNotContainsString('"@@context"', (string) $contenu);
    }
}
