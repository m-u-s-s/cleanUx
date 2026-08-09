<?php

namespace App\Services\Dispatch;

use App\Models\AsapDispatchRequest;
use App\Models\Booking;
use App\Models\MissionAssignment;
use App\Notifications\Dispatch\SearchOutcomeNotification;
use App\Services\OrderEngine\AsapDispatchService;
use App\Support\Domain\AsapStatus;
use App\Support\Domain\BookingStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * PERSONNE N'A RÉPONDU — les trois sorties, et aucune n'est un cul-de-sac (consigne 8).
 *
 * Un écran d'attente qui finit sur « aucun professionnel disponible » est un bug produit : le client
 * a déjà décidé, il a déjà donné son adresse, et on lui rend un constat. Les trois issues sont donc
 * des ACTIONS, pas des explications :
 *
 *   1. ATTENDRE ENCORE — la recherche repart, les prestataires redevenus disponibles entrent.
 *   2. CONVERTIR EN RENDEZ-VOUS — la même commande, à une heure choisie. Sans re-saisir, sans
 *      repayer : c'est la même réservation qui change de mode.
 *   3. ANNULER — proprement : aucune mission fantôme, aucune offre vivante, aucun argent capturé.
 *
 * LE PAIEMENT N'A JAMAIS ÉTÉ CAPTURÉ à ce stade, et c'est structurel : l'autorisation Stripe exige
 * un prestataire assigné (« destination charge »). Une recherche sans acceptation n'a donc rien
 * encaissé. On le VÉRIFIE quand même avant d'annuler : une capture inattendue doit devenir un
 * incident visible, pas un client débité pour une intervention qui n'a jamais eu lieu.
 */
class SearchOutcomeService
{
    public function __construct(
        protected DispatchEngine $engine,
        protected AsapDispatchService $searches,
    ) {}

    /**
     * « Continuer à attendre » — la recherche repart, plus large.
     *
     * Les exclusions ne sont PAS remises à zéro : réoffrir la course à qui vient de la refuser
     * ferait vibrer son téléphone pour rien et n'apporterait aucun candidat de plus. Ceux qui
     * n'avaient jamais été joints — hors ligne il y a trois minutes, en ligne maintenant — entrent
     * naturellement, puisque la liste est recalculée à chaque offre.
     */
    public function keepWaiting(AsapDispatchRequest $search): AsapDispatchRequest
    {
        if ($search->status !== AsapStatus::EXPIRED) {
            return $search;
        }

        $relancee = $this->engine->relaunch(
            $this->searches->transition($search, AsapStatus::SEARCHING),
        );

        $this->notifyClient($relancee, 'relaunched');

        return $relancee;
    }

    /**
     * « Convertir en rendez-vous » — la même réservation, à une heure choisie.
     *
     * SANS RE-SAISIR NI REPAYER. La réservation existe déjà, avec son devis figé, ses réponses au
     * questionnaire et son adresse : la renvoyer dans le parcours de commande ferait tout
     * recommencer, et c'est exactement le moment où un client abandonne.
     *
     * La recherche immédiate est CLÔTURÉE, pas laissée ouverte : deux chemins vivants pour la même
     * réservation produiraient deux prestataires.
     *
     * @throws ValidationException si la réservation n'existe plus ou si la date est passée
     */
    public function convertToScheduled(AsapDispatchRequest $search, Carbon $scheduledAt): Booking
    {
        $booking = $search->booking;

        if (! $booking) {
            throw ValidationException::withMessages([
                'dispatch' => ['Cette demande n’est plus rattachée à une réservation.'],
            ]);
        }

        if ($scheduledAt->isPast()) {
            throw ValidationException::withMessages([
                'scheduled_at' => ['Choisissez un créneau à venir.'],
            ]);
        }

        return DB::transaction(function () use ($search, $booking, $scheduledAt) {
            // La recherche immédiate se ferme AVANT que le planifié ne démarre : l'inverse laisserait
            // une fenêtre où les deux chaînes vivent.
            $this->closeSearch($search, 'converted_to_scheduled');
            $this->withdrawLiveOffers($booking);

            $booking->update([
                'booking_mode' => 'scheduled',
                'scheduled_at' => $scheduledAt,
                'scheduled_date' => $scheduledAt->toDateString(),
                'scheduled_time' => $scheduledAt->format('H:i:s'),
                'date' => $scheduledAt->toDateString(),
                'heure' => $scheduledAt->format('H:i:s'),
                'status' => BookingStatus::EN_ATTENTE,
                'asap_deadline_at' => null,
            ]);

            // Le flux planifié reprend la main : offre à long délai au mieux classé, puis repli.
            $this->engine->openScheduled($booking->fresh());

            $this->notifyClient($search->fresh(), 'converted');

            return $booking->fresh();
        });
    }

    /**
     * « Annuler » — proprement, et sans argent capturé.
     *
     * @return array{cancelled: bool, captured: bool}
     */
    public function cancelAndRelease(AsapDispatchRequest $search, ?string $reason = null): array
    {
        $booking = $search->booking;

        $capture = $booking !== null && $booking->payment_captured_at !== null;

        return DB::transaction(function () use ($search, $booking, $reason, $capture) {
            $this->closeSearch($search, $reason ?? 'cancelled_by_client');

            if ($booking) {
                $this->withdrawLiveOffers($booking);

                $booking->update([
                    'status' => BookingStatus::ANNULE,
                    'cancelled_at' => now(),
                    /*
                     * DEUX COLONNES DU MÊME NOM, DEUX TYPES.
                     *
                     * `bookings.cancelled_by` est un `bigint unsigned` — l'IDENTIFIANT de qui
                     * annule ; `asap_dispatch_requests.cancelled_by` est une chaîne — le RÔLE.
                     * Écrire « client » dans la première passe sous SQLite, qui accepte tout, et
                     * échoue en MySQL strict au moment précis où un client annule.
                     * Voir [[test-suite-sqlite-blindness]].
                     */
                    'cancelled_by' => $booking->client_id,
                    'cancellation_reason' => $reason ?? 'Aucun professionnel trouvé',
                ]);

                /*
                 * L'AUTORISATION EST LIBÉRÉE, la capture est un INCIDENT.
                 *
                 * Le paiement attend l'assignation : une recherche sans acceptation n'a rien
                 * encaissé. Si une capture existe malgré tout, c'est une anomalie qu'on écrit
                 * plutôt que de la corriger en silence — un remboursement automatique masquerait
                 * la cause et la ferait revenir.
                 */
                if ($capture) {
                    Log::error('SearchOutcomeService: annulation d’une recherche déjà encaissée', [
                        'booking_id' => $booking->id,
                        'search_id' => $search->id,
                        'captured_at' => $booking->payment_captured_at?->toIso8601String(),
                    ]);
                } elseif ($booking->payment_status === 'authorized') {
                    $booking->update([
                        'payment_status' => 'cancelled',
                        'payment_cancelled_at' => now(),
                    ]);
                }
            }

            $this->notifyClient($search->fresh(), 'cancelled');

            return ['cancelled' => true, 'captured' => $capture];
        });
    }

    /** Ferme la recherche quel que soit son état de départ — expirée ou encore en cours. */
    protected function closeSearch(AsapDispatchRequest $search, string $reason): void
    {
        $search->update([
            'status' => AsapStatus::CANCELLED,
            'cancelled_at' => now(),
            'cancelled_by' => 'client',
            'cancellation_reason' => $reason,
            // Aucun frais : personne ne s'est déplacé. Facturer une recherche infructueuse serait
            // faire payer notre propre incapacité à trouver quelqu'un.
            'cancellation_fee_cents' => 0,
        ]);
    }

    /**
     * Aucune offre vivante ne survit à une sortie.
     *
     * Sans ce geste, un prestataire garde une modale ouverte sur une course que le client vient
     * d'annuler ou de convertir : il accepte, et découvre une erreur. C'est ainsi qu'on apprend à
     * ne plus croire les offres.
     */
    protected function withdrawLiveOffers(Booking $booking): void
    {
        $mission = $booking->resolveMission();

        if (! $mission) {
            return;
        }

        MissionAssignment::query()
            ->where('mission_id', $mission->id)
            ->where('assignment_status', 'assigned')
            ->get()
            ->each(function (MissionAssignment $offre) {
                $offre->update([
                    'assignment_status' => 'cancelled',
                    'declined_at' => now(),
                    'decline_reason' => 'Demande retirée par le client',
                ]);

                $this->engine->withdraw($offre, 'cancelled');
            });
    }

    /** Le client est prévenu à CHAQUE étape : relancée, convertie, annulée. */
    protected function notifyClient(AsapDispatchRequest $search, string $outcome): void
    {
        $reservation = $search->booking;
        $client = $reservation !== null ? $reservation->client : $search->draft?->client;

        if (! $client) {
            return;
        }

        try {
            $client->notify(new SearchOutcomeNotification($search, $outcome));
        } catch (\Throwable $e) {
            // Soft-fail : une notification qui échoue ne doit pas annuler l'action que le client
            // vient de demander.
            Log::warning('SearchOutcomeService: notification client impossible', [
                'search_id' => $search->id,
                'outcome' => $outcome,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
