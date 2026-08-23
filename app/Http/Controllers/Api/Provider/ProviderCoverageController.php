<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Services\Catalog\ProviderCoverageWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** « CE QUE JE FAIS, ET OÙ » — lu et écrit par l'application prestataire. */
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

        // On rend CE QUI A ÉTÉ RETENU, pas ce qui a été demandé.
        return response()->json([
            'ok' => true,
            'data' => [
                'trade_ids' => $ecrit['trades'],
                'zone_ids' => $ecrit['zones'],
            ],
        ]);
    }
}
