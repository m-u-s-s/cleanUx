<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/** L'annuaire d'administration que voit l'application mobile. */
class AdminCatalogController extends Controller
{
    public function __invoke(): JsonResponse
    {
        /** @var list<array{key: string, title: string, group: string, icon: string, coverage: string, routes: list<string>}> $modules */
        $modules = config('admin_console.modules', []);

        $groups = [];

        foreach (config('admin_console.groups', []) as $key => $title) {
            $groups[] = [
                'key' => $key,
                'title' => $title,
                'modules' => array_values(array_map(
                    fn (array $module) => [
                        'key' => $module['key'],
                        'title' => $module['title'],
                        'icon' => $module['icon'],
                        'coverage' => $module['coverage'],
                        // La route principale du module. Les sous-routes (détail, export) sont
                        // rattachées au module côté registre pour que l'inventaire soit complet,
                        // mais elles ne sont pas des destinations d'annuaire.
                        'route' => $module['routes'][0],
                        // Les ressources SECONDAIRES du module.
                        'resources' => $this->ressourcesSecondairesDe($module),
                    ],
                    array_filter($modules, fn (array $module) => $module['group'] === $key),
                )),
            ];
        }

        $covered = count(array_filter($modules, fn (array $m) => $m['coverage'] !== 'pending'));

        return response()->json([
            'ok' => true,
            'groups' => $groups,
            'counts' => [
                'total' => count($modules),
                'covered' => $covered,
                'pending' => count($modules) - $covered,
            ],
        ]);
    }

    /**
     * Les ressources secondaires déclarées par un module.
     *
     * @param  array<string, mixed>  $module
     * @return list<string>
     */
    private function ressourcesSecondairesDe(array $module): array
    {
        return array_values(array_map('strval', (array) ($module['resources'] ?? [])));
    }
}
