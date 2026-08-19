<?php

namespace App\Services\Missions;

use App\Models\Booking;
use App\Models\CancellationExemptReason;
use App\Models\CancellationQuestionOption;
use App\Notifications\MissionEnRetardNotification;
use App\Services\Cancellation\CancellationAnswerVerifier;
use App\Services\Cancellation\CancellationExemptQuota;
use Illuminate\Support\Carbon;

/**
 * LE MINUTEUR DE RETARD — dire le retard AVANT que le client ne le découvre.
 *
 * Le fait était déjà mesuré, et pour un seul usage : décider si l'option « il est en retard »
 * pouvait s'afficher dans le formulaire d'annulation. Autrement dit, la plateforme ne parlait du
 * retard qu'à la personne qui avait déjà renoncé — elle constatait l'échec au lieu de l'éviter.
 *
 * ── LE RETARD EST UN FAIT, L'HEURE ANNONCÉE EST UNE PROMESSE ─────────────────────────────────
 *
 * Ce service tient les deux séparément. Le fait vient de `CancellationAnswerVerifier`, seule
 * mesure du dépôt : le minuteur et le formulaire d'annulation doivent basculer à la même minute,
 * sans quoi un client averti d'un retard de vingt-deux minutes se verrait refuser le motif « il
 * est en retard » et lirait une panne. La promesse, elle, vit sur la réservation, et son absence
 * est une information : personne n'a répondu.
 *
 * ── LES TROIS ISSUES ─────────────────────────────────────────────────────────────────────────
 *
 * Attendre, reprogrammer, annuler sans frais. Ce service ne les exécute pas — chacune a déjà son
 * tuyau, et en ouvrir un quatrième créerait une seconde façon d'annuler. Il dit seulement
 * lesquelles sont réellement ouvertes, pour qu'aucun bouton ne soit proposé puis refusé.
 */
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
     * Une heure annoncée qui est elle-même dépassée ne rassure plus personne : on la laisse
     * visible, mais le client verra qu'elle est passée. C'est volontaire — l'effacer donnerait
     * l'impression que rien n'a été promis, et c'est la promesse non tenue qui compte.
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

    /**
     * L'ANNULATION SANS FRAIS EST-ELLE RÉELLEMENT OUVERTE ?
     *
     * On ne redécide pas : on relit le questionnaire. Une option active portant la vérification
     * « retard », rattachée à un motif exempté qui exonère encore cette personne. Recalculer ici
     * ferait deux barèmes pour une même règle — et le jour où l'exploitation change le plafond
     * depuis la console, un seul des deux suivrait.
     */
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

    /**
     * PRÉVENIR LE CLIENT — une fois, et une seule.
     *
     * La commande repasse toutes les cinq minutes ; sans le tampon, un retard d'une heure enverrait
     * douze notifications identiques, ce qui est la façon la plus sûre de faire couper les
     * notifications par celui qu'on voulait aider.
     *
     * Rend `true` seulement si l'annonce vient d'être faite.
     */
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
     * Un retard annoncé avec une heure d'arrivée se gère ; un retard muet se subit. Le motif est
     * facultatif et court : on veut « embouteillage », pas un récit.
     *
     * L'heure annoncée n'est pas contrainte d'être dans le futur — un prestataire qui répond avec
     * cinq minutes de décalage écrirait une heure déjà passée, et refuser sa réponse pour cela le
     * laisserait muet, ce qui est pire pour le client.
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
