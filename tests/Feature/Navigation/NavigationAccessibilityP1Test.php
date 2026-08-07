<?php

namespace Tests\Feature\Navigation;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * P1 — accessibilité de la navigation. Fige les entrées de menu de pages qui n'étaient
 * atteignables qu'en tapant leur URL. Chaque page autrefois orpheline doit (a) être une route
 * réellement enregistrée et (b) être référencée par la navigation, pour qu'on puisse y arriver.
 *
 * CE TEST LISAIT LES FICHIERS BLADE. Il vérifiait qu'un nom de route apparaissait dans
 * `navigation-menu.blade.php` ou dans un layout société — ce qui était juste tant que les liens y
 * vivaient. Ils vivent désormais dans `config/modules.php`, seul registre, servi à la fois à la
 * navbar allégée et à la page Modules. L'assertion suit le registre : elle exige toujours une
 * porte, simplement là où les portes sont désormais déclarées.
 */
class NavigationAccessibilityP1Test extends TestCase
{
    /** Une route est « dans la navigation » si le catalogue lui donne une case. */
    private function assertDansLeCatalogue(string $nomDeRoute, string $contexte): void
    {
        $this->assertTrue(Route::has($nomDeRoute), "$nomDeRoute doit être une route enregistrée");

        $cases = collect(config('modules.catalogue'))
            ->where('context', $contexte)
            ->pluck('route')
            ->all();

        $this->assertContains(
            $nomDeRoute,
            $cases,
            "$nomDeRoute doit avoir une case dans le contexte $contexte de config/modules.php"
        );
    }

    public function test_client_company_nav_links_to_contracts(): void
    {
        $this->assertDansLeCatalogue('client-company.contracts', 'client-company');
    }

    public function test_admin_nav_links_to_feature_flags_manager(): void
    {
        $this->assertDansLeCatalogue('admin.feature-flags.manager', 'admin');
    }

    /**
     * Garde : la couverture de navigation déjà validée ne doit pas régresser — ces pages
     * centrales restent joignables depuis le répertoire des modules.
     */
    public function test_core_admin_pages_remain_in_nav(): void
    {
        foreach ([
            'admin.utilisateurs.manage',
            'admin.modules',
            'admin.orchestration',
            'admin.automation',
            'admin.b2b.operations',
        ] as $nomDeRoute) {
            $this->assertDansLeCatalogue($nomDeRoute, 'admin');
        }
    }
}
