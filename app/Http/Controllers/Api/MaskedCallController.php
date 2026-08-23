<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesClientBooking;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\MaskedCallSession;
use App\Models\Mission;
use App\Services\Missions\MissionAssignmentStatusService;
use App\Services\Safety\MaskedCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** LE NUMÉRO PAR LEQUEL SE JOINDRE, SANS SE DONNER SON NUMÉRO (F8). */
class MaskedCallController extends Controller
{
    use AuthorizesClientBooking;

    public function __construct(
        protected MaskedCallService $maskedCalls,
        protected MissionAssignmentStatusService $assignmentStatusService,
    ) {}

    /** Côté prestataire : par quel numéro joindre le client de cette mission. */
    public function pourLaMission(Request $request, Mission $mission): JsonResponse
    {
        // Le garde lève une exception ordinaire ; sans ce filet, un prestataire étranger à la mission recevait une erreur 500 au lieu d'un refus.
        try {
            $this->assignmentStatusService->assertAssignedToMission($mission, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        $booking = $mission->booking;
        $client = $booking?->client;

        if (! $booking || ! $client) {
            return response()->json(['data' => $this->indisponible('Aucun client rattaché à cette mission.')]);
        }

        return response()->json([
            'data' => $this->presenter(
                $this->session($booking, (int) $client->id, (int) $request->user()->id),
                pourLePrestataire: true,
            ),
        ]);
    }

    /** Côté client : par quel numéro joindre le prestataire de cette réservation. */
    public function pourLaReservation(Request $request, Booking $booking): JsonResponse
    {
        $this->assertClientPeutVoirLaReservation($request->user(), $booking);

        $prestataire = $booking->employe ?? $booking->assignedProvider;

        if (! $prestataire) {
            // Avant l'assignation, il n'y a personne à joindre — et le dire est plus utile qu'un
            // numéro qui ne sonnerait nulle part.
            return response()->json(['data' => $this->indisponible('Aucun prestataire n’est encore assigné.')]);
        }

        return response()->json([
            'data' => $this->presenter(
                $this->session($booking, (int) $booking->client_id, (int) $prestataire->id),
                pourLePrestataire: false,
            ),
        ]);
    }

    /** La session active de ce trio, ou rien. ON NE CRÉE PAS ICI. */
    protected function session(Booking $booking, int $clientId, int $prestataireId): ?MaskedCallSession
    {
        return MaskedCallSession::query()
            ->where('booking_id', $booking->id)
            ->where('client_user_id', $clientId)
            ->where('provider_user_id', $prestataireId)
            ->where('status', MaskedCallSession::STATUS_ACTIVE)
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function presenter(?MaskedCallSession $session, bool $pourLePrestataire): array
    {
        if (! $session || ! $session->isActive()) {
            return $this->indisponible('La ligne n’est pas ouverte pour cette intervention.');
        }

        return [
            'available' => true,
            // Le numéro à composer. C'est le SEUL numéro réel que cette réponse contient, et il
            // appartient à la plateforme.
            'proxy_number' => $session->proxy_phone_number,
            // Assez pour reconnaître son interlocuteur, jamais assez pour le rappeler ailleurs.
            'masked_peer_number' => $pourLePrestataire
                ? $session->client_phone_masked
                : $session->provider_phone_masked,
            'expires_at' => $session->expires_at?->toIso8601String(),
            'message' => $session->proxy_phone_number
                ? null
                // Sans configuration du fournisseur, la session existe en base mais aucun numéro
                // n'a été réservé. Le dire vaut mieux qu'un champ vide qu'on prendrait pour un bug.
                : 'La ligne masquée n’est pas encore active sur cet environnement.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function indisponible(string $raison): array
    {
        return [
            'available' => false,
            'proxy_number' => null,
            'masked_peer_number' => null,
            'expires_at' => null,
            'message' => $raison,
        ];
    }
}
