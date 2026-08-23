<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use App\Models\AsapDispatchNotification;
use App\Models\AsapDispatchRequest;
use App\Models\MissionAssignment;
use App\Services\Dispatch\MissionDispatchService;
use App\Support\Domain\AsapStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Les courses immédiates proposées à un prestataire. */
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

    /** Le prestataire prend la course. UNE SEULE VOIE D'ACCEPTATION. */
    public function accept(Request $request, AsapDispatchRequest $asapRequest): JsonResponse
    {
        $offer = $this->offerFor($request, $asapRequest);

        $assignment = MissionAssignment::query()
            ->where('mission_id', $asapRequest->mission_id)
            ->where('user_id', $request->user()->id)
            ->where('assignment_status', 'assigned')
            ->orderByDesc('id')
            ->first();

        if (! $assignment) {
            // Aucune offre nominative vivante : la course est partie, ou elle ne lui a jamais ete
            // proposee autrement que par la liste. 409 et non 422 — ce n'est pas une saisie
            // invalide.
            return response()->json([
                'message' => 'Cette course vient d’être prise par un autre professionnel.',
            ], 409);
        }

        try {
            app(MissionDispatchService::class)->accept($assignment);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => 'Cette course vient d’être prise par un autre professionnel.',
            ], 409);
        }

        $offer->update(['seen_at' => $offer->seen_at ?? now()]);

        return response()->json([
            'data' => [
                'asap_dispatch_request_id' => $asapRequest->id,
                'status' => $asapRequest->fresh()->status,
                'booking_id' => $asapRequest->booking_id,
            ],
        ]);
    }

    /** Le prestataire passe son tour. */
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

    /** La proposition faite à CE prestataire, ou 404. */
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
