<?php

namespace App\Services\Cancellation;

use App\Models\Booking;
use App\Models\CancellationQuestionOption;
use App\Models\Mission;
use App\Models\TripTrackingSession;
use App\Support\Domain\MissionStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * CE QUI SÉPARE UN QUESTIONNAIRE D'UN MENU D'ÉVITEMENT DE FRAIS.
 *
 * Un formulaire entièrement déclaratif n'est pas un questionnaire : c'est une liste de raisons de
 * ne rien payer, et tout le monde coche la plus avantageuse. Chaque option de ce module est donc
 * soit confrontée à un fait que le serveur détient, soit porteuse d'une conséquence pour celui qui
 * la coche.
 *
 * ── UNE OPTION NON VÉRIFIÉE N'EST PAS PROPOSÉE ───────────────────────────────────────────────
 *
 * Et surtout pas proposée puis refusée. Cocher « le prestataire est en retard » pour s'entendre
 * répondre « non » se lit comme une panne, pas comme une règle — et la personne recommence.
 * L'option disparaît simplement de la liste quand le fait ne la soutient pas.
 */
class CancellationAnswerVerifier
{
    /**
     * Cette option peut-elle être proposée sur cette réservation ?
     */
    public function estSoutenue(CancellationQuestionOption $option, Booking $booking): bool
    {
        return match ($option->verification) {
            CancellationQuestionOption::VERIF_RETARD => $this->leProviderEstEnRetard($booking),
            CancellationQuestionOption::VERIF_DEPLACEMENT => $this->ilSEstDeplace($booking),
            CancellationQuestionOption::VERIF_CLIENT_INJOIGNABLE => $this->leClientNeRepondPas($booking),
            default => true,
        };
    }

    /**
     * LE RETARD SE MESURE, IL NE SE DÉCLARE PAS.
     *
     * Deux conditions : l'heure prévue est passée d'au moins la tolérance, et l'intervention n'a
     * PAS démarré. Sans la seconde, un client pourrait invoquer le retard sur une mission commencée
     * avec dix minutes de décalage et déjà à moitié faite.
     */
    public function leProviderEstEnRetard(Booking $booking): bool
    {
        $prevu = $booking->scheduled_at
            ?? ($booking->date && $booking->heure
                ? Carbon::parse($booking->date->format('Y-m-d').' '.substr((string) $booking->heure, 0, 8))
                : null);

        if ($prevu === null) {
            return false;
        }

        $tolerance = max(0, (int) Config::get('missions.late_tolerance_minutes', 15));

        if (Carbon::now()->lessThan(Carbon::parse($prevu)->addMinutes($tolerance))) {
            return false;
        }

        $mission = Mission::query()->where('booking_id', $booking->id)->latest('id')->first();

        return $mission === null || $mission->actual_start_at === null;
    }

    /**
     * S'EST-IL RÉELLEMENT DÉPLACÉ ?
     *
     * Soutient « adresse introuvable ou inaccessible » : quelqu'un qui n'a pas bougé n'a pas pu
     * constater qu'une adresse était introuvable. Le seuil écarte les quelques mètres d'un GPS qui
     * dérive sur place.
     */
    public function ilSEstDeplace(Booking $booking): bool
    {
        $seuil = max(0, (int) Config::get('missions.movement_threshold_m', 300));

        return TripTrackingSession::query()
            ->where('booking_id', $booking->id)
            ->where('total_distance_m', '>=', $seuil)
            ->exists();
    }

    /**
     * A-T-ON TENTÉ DE JOINDRE LE CLIENT ?
     *
     * Le « tout va bien ? » a-t-il été envoyé sans réponse, ou le prestataire attend-il sur place
     * depuis assez longtemps ? Sans cette vérification, « le client ne répond pas » deviendrait le
     * motif universel d'un prestataire qui préfère repartir.
     */
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
