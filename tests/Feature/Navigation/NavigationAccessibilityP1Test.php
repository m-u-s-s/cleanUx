<?php

namespace Tests\Feature\Navigation;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** P1 — accessibilité de la navigation. */
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

    /** Garde : la couverture de navigation déjà validée ne doit pas régresser — ces pages centrales restent joignables depuis le répertoire des modules. */
    public function test_core_admin_pages_remain_in_nav(): void
    {
        // Les cinq routes relevees ensemble : un catalogue en retard l'est sur plusieurs entrees
        // a la fois, et chacune est un ecran qu'aucun menu n'atteint.
        $cases = collect(config('modules.catalogue'))->where('context', 'admin')->pluck('route')->all();
        $ecarts = [];

        foreach ([
            'admin.utilisateurs.manage',
            'admin.modules',
            'admin.orchestration',
            'admin.automation',
            'admin.b2b.operations',
        ] as $nomDeRoute) {
            if (! Route::has($nomDeRoute)) {
                $ecarts[] = "{$nomDeRoute} : route non enregistree";
            } elseif (! in_array($nomDeRoute, $cases, true)) {
                $ecarts[] = "{$nomDeRoute} : aucune case dans le contexte « admin »";
            }
        }

        $this->assertSame([], $ecarts, 'Ces ecrans ne sont atteignables par aucun menu.');
    }
}
