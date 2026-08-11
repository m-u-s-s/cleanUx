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

/**
 * LE BUDGET (E4), LA PROTECTION (E6) ET L'ASSISTANT DE COMMANDE (E5), SUR LE TÉLÉPHONE.
 *
 * POURQUOI CES TROIS-LÀ SUR MOBILE. Le budget se consulte quand la question se pose — souvent en
 * recevant une facture, sur son téléphone. La protection se consulte au pire moment : quand quelque
 * chose vient de se casser, et qu'on n'est pas devant un ordinateur. L'assistant, lui, est
 * précisément fait pour ceux qui ne veulent pas naviguer dans un catalogue à deux niveaux — la
 * situation par excellence d'un petit écran.
 *
 * CHAQUE LECTURE EST BORNÉE À L'APPELANT, sans exception : ces trois surfaces disent ce que
 * quelqu'un dépense, ce qui le couvre et ce qu'il réclame.
 */
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

    /**
     * Interpréter un besoin décrit en texte (E5).
     *
     * SOUS DRAPEAU. Coupé, le point d'entrée répond 404 plutôt que de rendre une interprétation
     * vide : l'application saurait alors qu'il n'existe pas, au lieu de croire que l'assistant n'a
     * rien compris.
     */
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
