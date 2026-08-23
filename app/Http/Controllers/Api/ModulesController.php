<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Navigation\ModuleCatalogue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Le catalogue des modules, servi aux applications mobiles. */
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
                        // Le CHEMIN, et non le nom de route : le mobile ouvre ces modules dans l'hôte WebView, qui ne sait rien du routeur Laravel.
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
