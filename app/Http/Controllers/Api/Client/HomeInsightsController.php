<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Ai\OrderIntentInterpreter;
use App\Services\Client\HomeBudgetService;
use App\Services\Client\ProtectionOverviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/** LE BUDGET (E4), LA PROTECTION (E6) ET L'ASSISTANT DE COMMANDE (E5), SUR LE TÉLÉPHONE. */
class HomeInsightsController extends Controller
{
    public function budget(Request $request): JsonResponse
    {
        // Borné : une fenêtre venue du téléphone ne doit pas faire scanner dix ans de
        // réservations à chaque ouverture d'écran.
        $mois = max(1, min(36, (int) $request->query('months', 12)));

        return response()->json([
            'data' => app(HomeBudgetService::class)->pour(
                Auth::user(),
                Carbon::now()->subMonths($mois)->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ),
        ]);
    }

    public function protection(): JsonResponse
    {
        return response()->json([
            'data' => app(ProtectionOverviewService::class)->pour(Auth::user()),
        ]);
    }

    /** Interpréter un besoin décrit en texte (E5). SOUS DRAPEAU. */
    public function interpret(Request $request): JsonResponse
    {
        abort_unless(feature('ai_order_assistant'), 404);

        $donnees = $request->validate([
            'description' => ['required', 'string', 'max:1000'],
        ]);

        return response()->json([
            // `confidence` et `source` VOYAGENT AVEC : l'application doit pouvoir dire « nous
            // pensons que c'est de la plomberie » plutôt que d'imposer un métier deviné.
            'data' => app(OrderIntentInterpreter::class)->interpreter($donnees['description']),
        ]);
    }
}
