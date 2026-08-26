<?php

namespace Tests\Feature\DesignSystem;

use App\Livewire\Admin\ProductEmailsCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * UN APERCU D'EMAIL EST UN DOCUMENT : il vit dans un cadre, pas dans la page.
 *
 * `{!! $previewHtml !!}` injectait un document COMPLET — `<html>`, `<head>`, `<body>` — dans
 * le corps de la page d'administration. Le navigateur ne cree pas un second document : il
 * fusionne les attributs du `<body>` de l'e-mail avec celui de la page. Le gabarit portant
 * `style="background:#f8fafc"`, un style EN LIGNE se posait sur le `<body>` reel — et un
 * style en ligne bat toutes les regles CSS, y compris le fond du theme.
 *
 * Mesure en mode sombre :
 *   avant  /admin/outils  body = rgb(248,250,252)   (les autres pages : rgb(10,14,26))
 *   apres  /admin/outils  body = rgb(10,14,26)
 *
 * Une page d'administration ENTIERE en clair, avec du texte clair dessus. Aucun des cinq
 * criteres du harnais ne le voyait : ils mesurent le debordement, les cibles tactiles et la
 * lisibilite du texte, pas la couleur du fond d'une page authentifiee.
 */
class UnApercuDEmailNeDeteintPasSurLaPageTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create([
            'permissions' => ['perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    public function test_l_apercu_est_rendu_dans_un_cadre_isole(): void
    {
        $this->actingAs($this->admin());

        $rendu = Livewire::test(ProductEmailsCenter::class)->html();

        $this->assertStringContainsString('<iframe', $rendu);
        $this->assertStringContainsString('srcdoc', $rendu);

        // `sandbox` sans `allow-scripts` : un gabarit ne doit rien pouvoir executer.
        $this->assertMatchesRegularExpression('/<iframe[^>]*\ssandbox(?![-\w=])/', $rendu);
        $this->assertStringNotContainsString('allow-scripts', $rendu);
    }

    /**
     * TEMOIN — LE DOCUMENT DE L'EMAIL N'ATTEINT PLUS LE DOM DE LA PAGE.
     *
     * C'est le point qui compte : `srcdoc` porte le document en ATTRIBUT, donc echappe. Une
     * balise `<body>` vivante dans le rendu signifierait que la fusion recommence.
     */
    public function test_temoin_aucune_balise_de_document_ne_reste_vivante(): void
    {
        $this->actingAs($this->admin());

        $rendu = Livewire::test(ProductEmailsCenter::class)->html();

        // Le gabarit d'e-mail en porte une : elle doit etre echappee, jamais vivante.
        $this->assertStringNotContainsString('<body', $rendu);
        $this->assertStringNotContainsString('<html', $rendu);
    }

    /**
     * TEMOIN POSITIF — l'apercu porte toujours QUELQUE CHOSE.
     *
     * Sans ce controle, un `srcdoc` vide passerait les deux tests precedents : le cadre
     * serait la, l'apercu ne montrerait rien.
     */
    public function test_temoin_l_apercu_n_est_pas_vide(): void
    {
        $this->actingAs($this->admin());

        $html = Livewire::test(ProductEmailsCenter::class)->get('previewHtml');

        $this->assertNotSame('', trim((string) $html));
        $this->assertStringContainsString('body', (string) $html, 'Le gabarit rend bien un document.');
    }
}
