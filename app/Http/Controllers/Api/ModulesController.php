<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Navigation\ModuleCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Le catalogue des modules, servi aux applications mobiles.
 *
 * POURQUOI PAR L'API PLUTÔT QU'EN DUR DANS LE MOBILE. Les applications portent déjà
 * `config/parity.php`, une seconde liste de modules tenue à côté de `config/modules.php`. En
 * écrire une troisième, en TypeScript, serait la troisième occasion d'en oublier une — et ce
 * dépôt a déjà payé le prix d'une table dupliquée.
 *
 * LE CONTEXTE SE DÉDUIT DU RÔLE, IL NE SE DEMANDE PAS. Le laisser passer par la requête
 * permettrait à un client de lire la liste des 90 modules d'administration : les cases ne
 * s'ouvriraient pas — chaque route reste gardée — mais la liste elle-même renseigne sur la
 * plateforme, ses fournisseurs et ses zones.
 */
class ModulesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $contexte = $user->roleCanonique()->contexteDeModules();

        $groupes = ModuleCatalogue::pourContexte($contexte)
            ->map(fn (array $groupe): array => [
                'category' => $groupe['category'],
                'label' => $groupe['label'],
                'modules' => array_map(
                    fn (array $module): array => [
                        'key' => $module['key'],
                        'label' => $module['label'],
                        'icon' => $module['icon'],
                        /*
                         * Le CHEMIN, et non le nom de route : le mobile ouvre ces modules dans
                         * l'hôte WebView, qui ne sait rien du routeur Laravel. Sans lui, la case
                         * serait un libellé qui ne mène nulle part — le défaut exact que la page
                         * Modules du web a corrigé.
                         */
                        'path' => '/'.ltrim(route($module['route'], [], false), '/'),
                    ],
                    $groupe['modules'],
                ),
            ])
            ->values();

        return response()->json([
            'context' => $contexte,
            'groups' => $groupes,
        ]);
    }
}
