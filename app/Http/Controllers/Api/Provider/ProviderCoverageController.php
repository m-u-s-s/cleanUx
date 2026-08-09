<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Catalog\ProviderCoverageWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * « CE QUE JE FAIS, ET OÙ » — lu et écrit par l'application prestataire.
 *
 * Ces deux tables — `trade_user` et `employee_zone_assignments` — sont EXACTEMENT celles que lit la
 * requête candidate du dispatch. Ce point d'entrée est donc la commande de ce qu'un prestataire
 * reçoit : décocher un métier arrête ses offres, cocher une zone les ouvre, sans déploiement.
 *
 * RIEN DE CE QUI ARRIVE N'EST CRU SUR PAROLE. Les identifiants viennent d'un formulaire :
 * `ProviderCoverageWriter` les valide contre le catalogue avant d'écrire. Accepter un métier fermé
 * donnerait une couverture qui ne peut produire aucune mission — et le prestataire attendrait des
 * offres qui ne viendraient jamais.
 */
class ProviderCoverageController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $prestataire = $request->user();

        return response()->json([
            'ok' => true,
            'data' => [
                'trade_ids' => $prestataire->trades()->pluck('trades.id')->map(fn ($id) => (int) $id)->all(),
                'zone_ids' => $prestataire->zoneAssignments()
                    ->where('is_active', true)
                    ->pluck('service_zone_id')
                    ->map(fn ($id) => (int) $id)
                    ->all(),
            ],
        ]);
    }

    public function update(Request $request, ProviderCoverageWriter $writer): JsonResponse
    {
        $donnees = $request->validate([
            'trade_ids' => ['required', 'array', 'min:1'],
            'trade_ids.*' => ['integer'],
            'zone_ids' => ['required', 'array', 'min:1'],
            'zone_ids.*' => ['integer'],
        ]);

        $ecrit = $writer->sync(
            $request->user(),
            array_map('intval', $donnees['trade_ids']),
            array_map('intval', $donnees['zone_ids']),
        );

        /*
         * On rend CE QUI A ÉTÉ RETENU, pas ce qui a été demandé.
         *
         * Un métier fermé au catalogue est écarté en silence côté écriture ; le taire à l'écran
         * laisserait le prestataire croire qu'il le couvre, et se demander pendant des semaines
         * pourquoi il ne reçoit rien.
         */
        return response()->json([
            'ok' => true,
            'data' => [
                'trade_ids' => $ecrit['trades'],
                'zone_ids' => $ecrit['zones'],
            ],
        ]);
    }
}
