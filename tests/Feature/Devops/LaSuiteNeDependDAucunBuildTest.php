<?php

namespace Tests\Feature\Devops;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * LA SUITE NE DOIT DÉPENDRE D'AUCUN `npm run build`.
 *
 * Toute page rendue passe par `@vite`, qui lève `ViteManifestNotFoundException` quand
 * `public/build/manifest.json` n'existe pas. Le test échoue alors en 500 et son message parle d'un
 * manifeste — jamais du code qu'il devait vérifier. C'est une classe entière de rouges qui ne
 * disent rien.
 *
 * DEUX FOIS PAYÉ SUR CE DÉPÔT. Un worktree neuf a produit 91 échecs n'ayant que cette cause. Puis
 * le job « Money/GDPR (MySQL, FK activées) » est passé au rouge : il n'installe pas Node, puisqu'il
 * vérifie des clés étrangères et non des écrans, et trois tests d'écran d'administration venaient
 * d'entrer dans son périmètre.
 *
 * `TestCase::setUp()` appelle donc `withoutVite()`. Ce test empêche que cet appel disparaisse — et
 * il ne se contente pas de chercher la ligne dans un fichier : il REND la directive et regarde ce
 * qui en sort.
 */
class LaSuiteNeDependDAucunBuildTest extends TestCase
{
    public function test_la_directive_vite_ne_reclame_aucun_manifeste(): void
    {
        $rendu = Blade::render("@vite(['resources/css/app.css', 'resources/js/app.js'])");

        $this->assertSame(
            '',
            trim($rendu),
            'La directive doit être neutralisée pendant les tests : sinon la suite exige un '
            .'`npm run build` préalable, et tout job qui n’installe pas Node vire au rouge pour '
            .'une raison qui ne concerne pas le code.',
        );
    }

    /**
     * LE TÉMOIN : la directive existe bel et bien et serait rendue sans la neutralisation.
     *
     * Sans lui, l'assertion précédente passerait au vert le jour où `@vite` disparaîtrait de Blade
     * ou serait renommée — en mesurant une directive inexistante plutôt qu'une directive muselée.
     */
    public function test_temoin_la_directive_vite_existe_toujours(): void
    {
        $compile = Blade::compileString("@vite(['resources/css/app.css'])");

        $this->assertStringContainsString(
            'Vite',
            $compile,
            'Blade ne compile plus `@vite` : l’assertion précédente ne mesure plus rien.',
        );
    }
}
