<?php

namespace App\Services\Missions;

use App\Models\Booking;
use App\Models\CancellationExemptReason;
use App\Models\CancellationQuestionOption;
use App\Notifications\MissionEnRetardNotification;
use App\Services\Cancellation\CancellationAnswerVerifier;
use App\Services\Cancellation\CancellationExemptQuota;
use Illuminate\Support\Carbon;

/** LE MINUTEUR DE RETARD — dire le retard AVANT que le client ne le découvre. */
class MissionDelayService
{
    public function __construct(
        private readonly CancellationAnswerVerifier $verificateur,
        private readonly CancellationExemptQuota $quota,
    ) {}

    /**
     * L'ÉTAT COMPLET, tel que les deux applications l'affichent.
     *
     * @return array{en_retard: bool, minutes: int|null, heure_prevue: string|null, annonce: array{arrivee_at: string|null, motif: string|null}|null, annulation_gratuite: bool, prevenu_at: string|null}
     */
    public function etat(Booking $booking): array
    {
        $minutes = $this->verificateur->minutesDeRetard($booking);
        $prevu = $this->verificateur->heurePrevue($booking);

        return [
            'en_retard' => $minutes !== null,
            'minutes' => $minutes,
            'heure_prevue' => $prevu?->toIso8601String(),
            'annonce' => $this->annonce($booking),
            'annulation_gratuite' => $minutes !== null && $this->annulationGratuiteOuverte($booking),
            'prevenu_at' => $booking->late_notified_at?->toIso8601String(),
        ];
    }

    /**
     * LA PROMESSE DU PRESTATAIRE, si elle tient encore.
     *
     * @return array{arrivee_at: string|null, motif: string|null}|null
     */
    private function annonce(Booking $booking): ?array
    {
        if ($booking->provider_delay_eta_at === null && $booking->provider_delay_reason === null) {
            return null;
        }

        return [
            'arrivee_at' => $booking->provider_delay_eta_at?->toIso8601String(),
            'motif' => $booking->provider_delay_reason,
        ];
    }

    /** L'ANNULATION SANS FRAIS EST-ELLE RÉELLEMENT OUVERTE ? */
    private function annulationGratuiteOuverte(Booking $booking): bool
    {
        $option = CancellationQuestionOption::query()
            ->where('verification', CancellationQuestionOption::VERIF_RETARD)
            ->where('is_active', true)
            ->whereNotNull('exempt_reason_id')
            ->first();

        if ($option === null) {
            return false;
        }

        $motif = CancellationExemptReason::query()->find($option->exempt_reason_id);

        if ($motif === null || ! $motif->is_active) {
            return false;
        }

        return $this->quota->exonereEncore($motif, $booking->client_id === null ? null : (int) $booking->client_id);
    }

    /** PRÉVENIR LE CLIENT — une fois, et une seule. */
    public function annoncerAuClient(Booking $booking): bool
    {
        if ($booking->late_notified_at !== null) {
            return false;
        }

        $minutes = $this->verificateur->minutesDeRetard($booking);

        if ($minutes === null) {
            return false;
        }

        $client = $booking->client;

        $booking->forceFill(['late_notified_at' => Carbon::now()])->save();

        if ($client !== null) {
            $client->notify(new MissionEnRetardNotification($booking, $minutes, $this->annonce($booking)));
        }

        return true;
    }

    /**
     * LE PRESTATAIRE RÉPOND — et c'est la seule chose qui évite l'annulation.
     *
     * @return array{en_retard: bool, minutes: int|null, heure_prevue: string|null, annonce: array{arrivee_at: string|null, motif: string|null}|null, annulation_gratuite: bool, prevenu_at: string|null}
     */
    public function annoncerParLePrestataire(Booking $booking, ?Carbon $arrivee, ?string $motif): array
    {
        $booking->forceFill([
            'provider_delay_eta_at' => $arrivee,
            'provider_delay_reason' => $motif === null || trim($motif) === '' ? null : mb_substr(trim($motif), 0, 180),
        ])->save();

        return $this->etat($booking->refresh());
    }
}
