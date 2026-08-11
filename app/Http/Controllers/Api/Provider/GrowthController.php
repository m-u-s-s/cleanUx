<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\AcademyCourse;
use App\Services\Payments\ExpressPayoutService;
use App\Services\Provider\AcademyService;
use App\Services\Provider\DailyRouteService;
use App\Services\Provider\DemandHeatmapService;
use App\Services\Provider\OfferStatsService;
use App\Services\Provider\QuestService;
use App\Services\Provider\TaxSummaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * CE QUI FAIT PROGRESSER UN PRESTATAIRE — heatmap (E12), objectifs (E13), cash-out (E14),
 * statistiques d'offres (E15), académie (E16), tournée du jour (E17/E34), fiscal (E18).
 *
 * POURQUOI CES SEPT SUR UN TÉLÉPHONE. Ce sont exactement les questions qu'on se pose EN TRAVAILLANT,
 * pas assis à un bureau : où me placer ce matin, où j'en suis de mon objectif, est-ce que ma journée
 * tient, est-ce que je peux être payé maintenant. Un prestataire indépendant n'a souvent pas
 * d'ordinateur du tout.
 *
 * LA TOURNÉE EST LA PLUS CRITIQUE DES SEPT. Elle se consulte le matin, en montant dans la voiture,
 * et elle dit si l'enchaînement de la journée tient. La renvoyer au web reviendrait à ne pas la
 * servir.
 *
 * TOUTES LES LECTURES SONT BORNÉES À L'APPELANT, sans exception : ces surfaces disent ce que
 * quelqu'un gagne, où il va, et comment il travaille.
 */
class GrowthController extends Controller
{
    /** E12 — où me placer, et à quelle heure. */
    public function heatmap(Request $request): JsonResponse
    {
        // Bornée : une fenêtre venue du téléphone ne doit pas faire scanner deux ans de recherches.
        $jours = max(7, min(90, (int) $request->query('days', 28)));

        return response()->json([
            'data' => app(DemandHeatmapService::class)->pourLaPeriode(
                Carbon::now()->subDays($jours),
                Carbon::now(),
                $request->query('trade_id') ? (int) $request->query('trade_id') : null,
            ),
            'meta' => [
                'days_observed' => $jours,
                // CE N'EST PAS UNE PROMESSE : l'application doit pouvoir le dire, sinon un pic
                // isolé se lit comme une tendance et déplace quelqu'un pour rien.
                'is_observation' => true,
            ],
        ]);
    }

    /** E13 — où j'en suis de mes objectifs. */
    public function quests(): JsonResponse
    {
        return response()->json([
            'data' => app(QuestService::class)->pour(Auth::user()),
        ]);
    }

    /** E15 — pourquoi je reçois moins de courses qu'avant. */
    public function offerStats(): JsonResponse
    {
        return response()->json([
            'data' => app(OfferStatsService::class)->pour(Auth::user()),
        ]);
    }

    /** E16 — le catalogue de formations, et ce que chacune débloque. */
    public function courses(): JsonResponse
    {
        return response()->json([
            'data' => app(AcademyService::class)->catalogue(Auth::user()),
        ]);
    }

    public function completeCourse(Request $request, int $courseId): JsonResponse
    {
        $donnees = $request->validate([
            'score_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $cours = AcademyCourse::query()
            ->where('is_published', true)
            ->findOrFail($courseId);

        $completion = app(AcademyService::class)->terminer(
            Auth::user(),
            $cours,
            $donnees['score_percent'] ?? null,
        );

        return response()->json([
            'data' => [
                'id' => $completion->id,
                'completed_at' => $completion->completed_at->toIso8601String(),
                // Le badge est un effet SOFT-FAIL : l'annoncer permet à l'application de dire
                // « badge en cours d'attribution » plutôt que de prétendre qu'il est acquis.
                'badge_granted' => $completion->badge_granted_at !== null,
            ],
        ], 201);
    }

    /** E17 + E34 — ma journée, et si elle tient. */
    public function dailyRoute(Request $request): JsonResponse
    {
        $jour = $request->query('date')
            ? Carbon::parse((string) $request->query('date'))
            : Carbon::now();

        return response()->json([
            'data' => app(DailyRouteService::class)->pourLaJournee(Auth::user(), $jour),
        ]);
    }

    /** E18 — mes revenus déclarables. */
    public function taxSummary(Request $request): JsonResponse
    {
        $annee = (int) $request->query('year', (string) Carbon::now()->year);

        return response()->json([
            'data' => app(TaxSummaryService::class)->pourLAnnee(Auth::user(), $annee),
        ]);
    }

    /** E14 — le devis d'un virement instantané, À AFFICHER AVANT le bouton. */
    public function expressQuote(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:0'],
        ]);

        return response()->json([
            // Les frais en EUROS, et le NET : « 1,5 % » se lit et ne se comprend pas.
            'data' => app(ExpressPayoutService::class)->devis((int) $donnees['amount_cents']),
        ]);
    }

    public function expressPayout(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $payout = app(ExpressPayoutService::class)->demander(Auth::user(), (int) $donnees['amount_cents']);
        } catch (ValidationException $e) {
            // « Le virement instantané demande au moins 20 € » est une règle à LIRE : la remplacer
            // par une erreur générique ferait recommencer la saisie.
            return response()->json([
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'data' => ['id' => $payout->id, 'status' => $payout->status],
        ], 201);
    }
}
