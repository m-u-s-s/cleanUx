<?php

namespace App\Services\Cancellation;

use App\Models\Booking;
use App\Models\CancellationQuestionOption;
use App\Models\Mission;
use App\Models\TripTrackingSession;
use App\Support\Domain\MissionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/** CE QUI SÉPARE UN QUESTIONNAIRE D'UN MENU D'ÉVITEMENT DE FRAIS. */
class CancellationAnswerVerifier
{
    /** Cette option peut-elle être proposée sur cette réservation ? */
    public function estSoutenue(CancellationQuestionOption $option, Booking $booking): bool
    {
        return match ($option->verification) {
            CancellationQuestionOption::VERIF_RETARD => $this->leProviderEstEnRetard($booking),
            CancellationQuestionOption::VERIF_DEPLACEMENT => $this->ilSEstDeplace($booking),
            CancellationQuestionOption::VERIF_CLIENT_INJOIGNABLE => $this->leClientNeRepondPas($booking),
            default => true,
        };
    }

    /** LE RETARD SE MESURE, IL NE SE DÉCLARE PAS. */
    public function leProviderEstEnRetard(Booking $booking): bool
    {
        return $this->minutesDeRetard($booking) !== null;
    }

    /** DE COMBIEN, ET LE MÊME CALCUL POUR TOUT LE MONDE. */
    public function minutesDeRetard(Booking $booking): ?int
    {
        $prevu = $this->heurePrevue($booking);

        if ($prevu === null) {
            return null;
        }

        $tolerance = max(0, (int) Config::get('missions.late_tolerance_minutes', 15));

        if (Carbon::now()->lessThan($prevu->copy()->addMinutes($tolerance))) {
            return null;
        }

        $mission = Mission::query()->where('booking_id', $booking->id)->latest('id')->first();

        if ($mission !== null && $mission->actual_start_at !== null) {
            return null;
        }

        return (int) $prevu->diffInMinutes(Carbon::now());
    }

    /** L'HEURE PRÉVUE, quelle que soit la colonne qui la porte. */
    public function heurePrevue(Booking $booking): ?Carbon
    {
        if ($booking->scheduled_at !== null) {
            return Carbon::parse($booking->scheduled_at);
        }

        if ($booking->date && $booking->heure) {
            return Carbon::parse($booking->date->format('Y-m-d').' '.substr((string) $booking->heure, 0, 8));
        }

        return null;
    }

    /** S'EST-IL RÉELLEMENT DÉPLACÉ ? */
    public function ilSEstDeplace(Booking $booking): bool
    {
        $seuil = max(0, (int) Config::get('missions.movement_threshold_m', 300));

        return TripTrackingSession::query()
            ->where('booking_id', $booking->id)
            ->where('total_distance_m', '>=', $seuil)
            ->exists();
    }

    /** A-T-ON TENTÉ DE JOINDRE LE CLIENT ? Le « tout va bien ? */
    public function leClientNeRepondPas(Booking $booking): bool
    {
        if ($booking->checkin_ping_sent_at !== null && $booking->checkin_ping_answered_at === null) {
            return true;
        }

        $mission = Mission::query()->where('booking_id', $booking->id)->latest('id')->first();

        if ($mission === null || $mission->status !== MissionStatus::ARRIVED) {
            return false;
        }

        $attente = max(0, (int) Config::get('missions.no_answer_wait_minutes', 10));

        $arrivee = $mission->assignments()->whereNotNull('arrived_at')->min('arrived_at');

        return $arrivee !== null
            && Carbon::parse($arrivee)->addMinutes($attente)->isPast();
    }
}
