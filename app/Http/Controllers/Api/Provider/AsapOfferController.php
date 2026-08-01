<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\AsapDispatchNotification;
use App\Models\AsapDispatchRequest;
use App\Services\OrderEngine\AsapDispatchService;
use App\Support\Domain\AsapStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Les courses immédiates proposées à un prestataire.
 *
 * L'autre bout de la notification poussée : le prestataire reçoit une alerte, ouvre l'application,
 * et doit trouver ici de quoi décider en trois secondes — le métier, la distance, le montant, et
 * deux boutons.
 *
 * PREMIER ARRIVÉ, PREMIER SERVI. La course part au premier qui accepte ; les autres reçoivent un
 * refus explicite plutôt qu'une erreur technique. Un prestataire qui appuie sur « accepter » et
 * voit un plantage croit à un bug de l'application, pas à une course déjà prise.
 */
class AsapOfferController extends Controller
{
    /** Ce qui est proposé à ce prestataire, en ce moment. */
    public function index(Request $request): JsonResponse
    {
        $offers = AsapDispatchNotification::query()
            ->pending()
            ->where('user_id', $request->user()->id)
            ->whereHas('request', fn ($q) => $q->where('status', AsapStatus::SEARCHING))
            ->with(['request.trade', 'request.draft'])
            ->orderByDesc('notified_at')
            ->get()
            ->map(fn (AsapDispatchNotification $offer) => $this->present($offer));

        return response()->json(['data' => $offers]);
    }

    /**
     * Le prestataire prend la course.
     *
     * Le verrou vit dans le service : deux prestataires peuvent appuyer à la même seconde, et le
     * second doit être refusé proprement plutôt que d'écraser le premier.
     */
    public function accept(Request $request, AsapDispatchRequest $asapRequest): JsonResponse
    {
        $offer = $this->offerFor($request, $asapRequest);

        try {
            $accepted = app(AsapDispatchService::class)->accept($asapRequest, $request->user());
        } catch (ValidationException $e) {
            // 409 et non 422 : ce n'est pas une saisie invalide, c'est une course déjà prise.
            return response()->json([
                'message' => 'Cette course vient d’être prise par un autre professionnel.',
            ], 409);
        }

        $offer->update(['seen_at' => $offer->seen_at ?? now()]);

        return response()->json([
            'data' => [
                'asap_dispatch_request_id' => $accepted->id,
                'status' => $accepted->status,
                'booking_id' => $accepted->item?->metadata['booking_id'] ?? null,
            ],
        ]);
    }

    /**
     * Le prestataire passe son tour.
     *
     * Le refus est ENREGISTRÉ, pas seulement affiché : sans trace, la course lui serait reproposée
     * au prochain élargissement de rayon, et il la refuserait à nouveau.
     */
    public function decline(Request $request, AsapDispatchRequest $asapRequest): JsonResponse
    {
        $offer = $this->offerFor($request, $asapRequest);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        $offer->update([
            'declined_at' => now(),
            'decline_reason' => $validated['reason'] ?? null,
        ]);

        return response()->json(['data' => ['declined' => true]]);
    }

    /**
     * La proposition faite à CE prestataire, ou 404.
     *
     * Une course ne se prend pas parce qu'on connaît son numéro : il faut qu'elle ait été proposée.
     * Sans cette vérification, n'importe quel prestataire raflerait les courses des autres zones en
     * énumérant des identifiants.
     */
    protected function offerFor(Request $request, AsapDispatchRequest $asapRequest): AsapDispatchNotification
    {
        $offer = AsapDispatchNotification::query()
            ->where('asap_dispatch_request_id', $asapRequest->id)
            ->where('user_id', $request->user()->id)
            ->first();

        abort_if($offer === null, 404);

        return $offer;
    }

    /** @return array<string, mixed> */
    protected function present(AsapDispatchNotification $offer): array
    {
        $request = $offer->request;

        return [
            'id' => $offer->id,
            'asap_dispatch_request_id' => $request->id,
            'trade' => $request->trade?->name,
            'distance_m' => $offer->distance_m,
            'distance_km' => round(((int) $offer->distance_m) / 1000, 1),
            // Le montant que le client a accepté, pas une estimation refaite ici : le prestataire
            // décide sur le chiffre qui l'engagera.
            'estimate_min_cents' => $request->draft?->estimate_min_cents,
            'estimate_max_cents' => $request->draft?->estimate_max_cents,
            'notified_at' => $offer->notified_at?->toIso8601String(),
            'waiting_seconds' => $request->elapsedSeconds(),
        ];
    }
}
