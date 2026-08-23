<?php

namespace Tests\Feature\Admin;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** La garantie « rien n'est oublié » de la console d'administration mobile. */
class AdminConsoleInventoryTest extends TestCase
{
    /**
     * Les routes GET de l'administration web, telles que le routeur les connaît.
     *
     * @return list<string>
     */
    private function webAdminRoutes(): array
    {
        $uris = [];

        foreach (Route::getRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if (! str_starts_with($route->uri(), 'admin/') && $route->uri() !== 'admin') {
                continue;
            }

            $uris[] = $route->uri();
        }

        return array_values(array_unique($uris));
    }

    /** @return list<string> */
    private function declaredRoutes(): array
    {
        $declared = [];

        foreach (config('admin_console.modules') as $module) {
            foreach ($module['routes'] as $uri) {
                $declared[] = $uri;
            }
        }

        return $declared;
    }

    public function test_chaque_page_admin_du_web_est_declaree(): void
    {
        $missing = array_values(array_diff($this->webAdminRoutes(), $this->declaredRoutes()));

        $this->assertSame([], $missing,
            'Pages admin absentes de config/admin_console.php : '.implode(', ', $missing));
    }

    public function test_aucune_route_declaree_n_est_morte(): void
    {
        $stale = array_values(array_diff($this->declaredRoutes(), $this->webAdminRoutes()));

        $this->assertSame([], $stale,
            'Routes déclarées qui n’existent plus dans le routeur : '.implode(', ', $stale));
    }

    public function test_aucune_route_n_est_declaree_deux_fois(): void
    {
        $duplicates = array_keys(array_filter(
            array_count_values($this->declaredRoutes()),
            fn (int $n) => $n > 1,
        ));

        $this->assertSame([], $duplicates,
            'Routes revendiquées par plusieurs modules : '.implode(', ', $duplicates));
    }

    public function test_chaque_module_est_bien_forme(): void
    {
        $groups = array_keys(config('admin_console.groups'));

        foreach (config('admin_console.modules') as $module) {
            $key = $module['key'] ?? '(clé manquante)';

            $this->assertNotEmpty($module['key'] ?? null, 'Module sans clé.');
            $this->assertNotEmpty($module['title'] ?? null, "Module {$key} sans titre.");
            $this->assertContains($module['group'] ?? null, $groups, "Groupe inconnu pour {$key}.");
            $this->assertNotEmpty($module['icon'] ?? null, "Module {$key} sans icône.");
            $this->assertNotEmpty($module['routes'] ?? [], "Module {$key} sans route.");
            $this->assertContains($module['coverage'] ?? null, ['pending', 'descriptor', 'report', 'screen'],
                "État de couverture invalide pour {$key}.");
        }
    }

    public function test_les_cles_de_module_sont_uniques(): void
    {
        $keys = array_column(config('admin_console.modules'), 'key');

        $this->assertSame(array_values(array_unique($keys)), $keys,
            'Deux modules partagent la même clé.');
    }

    public function test_chaque_groupe_declare_porte_au_moins_un_module(): void
    {
        $used = array_unique(array_column(config('admin_console.modules'), 'group'));
        $empty = array_values(array_diff(array_keys(config('admin_console.groups')), $used));

        // Un groupe vide serait une section d'annuaire qui s'affiche sans rien contenir.
        $this->assertSame([], $empty, 'Groupes sans module : '.implode(', ', $empty));
    }
}
