<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Catalog\RegistrationOptionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LES MÉTIERS ET LES ZONES OFFERTS À L'INSCRIPTION — une seule API, web et mobile.
 *
 * Le formulaire web et l'onboarding natif proposaient deux listes construites différemment : l'un
 * lisait `trades` sans filtre, l'autre ne proposait aucune zone. Un métier ouvert par
 * l'administration apparaissait donc d'un côté et pas de l'autre, et personne ne savait lequel
 * disait vrai.
 *
 * PUBLIC, ET C'EST OBLIGATOIRE : ce point d'entrée est appelé AVANT que le compte existe, donc
 * avant tout jeton. Le basculer derrière `auth:sanctum` viderait le formulaire d'inscription.
 *
 * Il ne rend que du CATALOGUE — des noms de métiers et de zones publiés. Aucune donnée personnelle,
 * aucun prix : la grille tarifaire ne se lit pas ici.
 */
class RegistrationOptionsController extends Controller
{
    public function __invoke(Request $request, RegistrationOptionsService $options): JsonResponse
    {
        $pays = $request->query('country');

        return response()->json([
            'ok' => true,
            'data' => $options->forCountry(is_string($pays) && $pays !== '' ? $pays : null),
        ]);
    }
}
