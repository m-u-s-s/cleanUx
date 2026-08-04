<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * L'annuaire d'administration que voit l'application mobile.
 *
 * Il sert le registre `config/admin_console.php` tel quel, groupé pour l'affichage.
 *
 * POURQUOI LES MODULES NON COUVERTS SONT SERVIS EUX AUSSI. Ne renvoyer que le couvert donnerait
 * une application qui a l'air complète, et un chantier dont personne — ni l'utilisateur, ni celui
 * qui le mène — ne peut mesurer l'avancement. Le mobile les affiche marqués « à venir » et
 * non navigables ; les compteurs disent exactement ce qui reste.
 *
 * L'ORDRE DES GROUPES EST CELUI DU REGISTRE. Il porte une intention de lecture (piloter, opérer,
 * puis administrer) ; le trier autrement ici la perdrait silencieusement.
 */
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
                        /*
                         * Les ressources SECONDAIRES du module.
                         *
                         * Une page web multi-modèles — « Opérations B2B » et ses contrats, ordres
                         * de travail et grilles — donne plusieurs descripteurs pour une seule
                         * entrée d'annuaire. Sans cette liste, le mobile ouvrirait la ressource
                         * principale et les autres resteraient écrites, correctes et
                         * inatteignables.
                         */
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
     * Passer par une méthode plutôt que de lire la clé sur place : l'analyse statique déduit la
     * forme du tableau depuis `config/admin_console.php`, où la plupart des modules n'ont pas cette
     * clé — elle la croit donc absente partout et refuse l'accès.
     *
     * @param  array<string, mixed>  $module
     * @return list<string>
     */
    private function ressourcesSecondairesDe(array $module): array
    {
        return array_values(array_map('strval', (array) ($module['resources'] ?? [])));
    }
}
