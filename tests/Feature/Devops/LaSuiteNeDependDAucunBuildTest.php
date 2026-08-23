<?php

namespace Tests\Feature\Devops;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** LA SUITE NE DOIT DÉPENDRE D'AUCUN `npm run build`. */
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

    /** LE TÉMOIN : la directive existe bel et bien et serait rendue sans la neutralisation. */
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
