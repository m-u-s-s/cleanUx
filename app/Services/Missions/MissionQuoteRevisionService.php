<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionFeatureSuspension;
use App\Models\MissionQuoteRevision;
use App\Models\User;
use App\Support\Domain\MissionEngine;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * LE NOUVEAU DEVIS — proposer, accepter, refuser.
 *
 * ── CE QU'IL EST, ET CE QU'IL N'EST PAS ──────────────────────────────────────────────────────
 *
 * Il remplace le prix parce que le devis était faux DÈS LE DÉPART : vingt mètres carrés annoncés,
 * deux cents constatés. Ce n'est pas un supplément — celui-ci ajoute une ligne à un devis juste,
 * pour un imprévu découvert EN TRAVAILLANT. La frontière est tenue par
 * {@see QuoteRevisionWindow} : dès que le prestataire a touché à quelque chose, cette porte se
 * ferme et il ne lui reste que le supplément.
 *
 * ── LE PRESTATAIRE ANNONCE UN SERVICE, JAMAIS UN TOTAL ───────────────────────────────────────
 *
 * {@see QuoteRevisionPricing} réapplique les remises. S'il tapait le total à payer, le code promo
 * du client disparaîtrait dans un chiffre rond.
 *
 * ── LA PREUVE EST OBLIGATOIRE ────────────────────────────────────────────────────────────────
 *
 * Motif ET photo. Sans elles, le client doit croire sur parole et l'arbitre doit trancher sans
 * matière — c'est-à-dire que l'abus devient gratuit.
 */
class MissionQuoteRevisionService
{
    public function __construct(
        private readonly QuoteRevisionWindow $fenetre,
        private readonly QuoteRevisionPricing $tarification,
        private readonly MissionAssignmentStatusService $affectations,
    ) {}

    /**
     * Le prestataire propose un prix révisé.
     *
     * @param  list<int>  $mediaIds
     *
     * @throws DomainException
     */
    public function proposer(
        Mission $mission,
        User $prestataire,
        int $prixServiceCents,
        string $motif,
        array $mediaIds,
        string $codeMotif = 'ecart_constate',
    ): MissionQuoteRevision {
        $this->affectations->assertAssignedToMission($mission, $prestataire);

        $etat = $this->fenetre->etat($mission);

        if ($etat['open'] !== true) {
            throw new DomainException((string) $etat['reason']);
        }

        $this->assertOptionOuverte($prestataire);

        if ($this->vivante($mission) !== null) {
            throw new DomainException(
                'Une révision attend déjà la réponse du client : retirez-la avant d’en proposer une autre.'
            );
        }

        if (trim($motif) === '') {
            throw new DomainException('Dites au client ce qui justifie ce prix.');
        }

        if ($mediaIds === []) {
            throw new DomainException(
                'Ajoutez au moins une photo : sans preuve, le client doit vous croire sur parole.'
            );
        }

        $reservation = $mission->booking;

        if ($reservation === null) {
            throw new DomainException('Cette mission n’a pas de réservation à réviser.');
        }

        $origine = $this->totalOrigineCents($mission);
        $revise = $this->tarification->recalculer($reservation, $prixServiceCents);

        if ($revise['total_cents'] === $origine) {
            throw new DomainException('Ce prix est celui du devis actuel : il n’y a rien à réviser.');
        }

        return MissionQuoteRevision::query()->create([
            'mission_id' => $mission->id,
            'booking_id' => $reservation->id,
            'proposed_by_user_id' => $prestataire->id,
            // GELÉ ICI : l'acceptation réécrira le devis de la réservation, et relire ce montant
            // après coup rendrait le NOUVEAU — le dossier de désaccord perdrait son chiffre.
            'original_total_cents' => $origine,
            'proposed_service_cents' => max(0, $prixServiceCents),
            'revised_total_cents' => $revise['total_cents'],
            'discount_breakdown' => $revise['breakdown'],
            'currency' => strtoupper((string) ($reservation->currency ?: 'EUR')),
            'reason_code' => $codeMotif,
            'reason_text' => trim($motif),
            'evidence_media_ids' => array_values(array_map('intval', $mediaIds)),
            'status' => MissionQuoteRevision::STATUT_PROPOSEE,
            'window_closes_at' => $etat['closes_at'] !== null
                ? Carbon::parse($etat['closes_at'])
                : Carbon::now()->addMinutes(30),
        ]);
    }

    /** La révision qui attend encore une réponse, s'il y en a une. */
    public function vivante(Mission $mission): ?MissionQuoteRevision
    {
        return MissionQuoteRevision::query()
            ->where('mission_id', $mission->id)
            ->where('status', MissionQuoteRevision::STATUT_PROPOSEE)
            ->latest('id')
            ->first();
    }

    /**
     * Le prestataire retire sa proposition — un geste honnête, qui doit rester possible.
     *
     * @throws DomainException
     */
    public function retirer(MissionQuoteRevision $revision, User $prestataire): MissionQuoteRevision
    {
        if (! $revision->attendLeClient()) {
            throw new DomainException('Cette révision a déjà reçu une réponse.');
        }

        if ((int) $revision->proposed_by_user_id !== (int) $prestataire->id) {
            throw new DomainException('Cette révision n’est pas la vôtre.');
        }

        $revision->forceFill([
            'status' => MissionQuoteRevision::STATUT_RETIREE,
            'responded_at' => Carbon::now(),
        ])->save();

        return $revision->fresh();
    }

    /**
     * L'option est-elle ouverte à ce prestataire ?
     *
     * @throws DomainException
     */
    private function assertOptionOuverte(User $prestataire): void
    {
        $suspension = MissionFeatureSuspension::query()
            ->where('user_id', $prestataire->id)
            ->where('feature', MissionFeatureSuspension::OPTION_REVISION)
            ->actives()
            ->latest('id')
            ->first();

        if ($suspension === null) {
            return;
        }

        throw new DomainException(
            $suspension->estDefinitive()
                ? 'La révision de devis vous a été retirée. Un administrateur peut la rétablir.'
                : 'La révision de devis est suspendue jusqu’au '.$suspension->ends_at->format('d/m/Y').'.'
        );
    }

    /**
     * LE TOTAL D'ORIGINE — la même source que la commission, et ce n'est pas un détail.
     *
     * `CommissionService::calculateForBooking()` lit `devis_estime ?? estimated_price`. Prendre un
     * autre chiffre ici ferait diverger ce qu'on annonce au client de ce qu'on reverse au
     * prestataire, et l'écart ne se verrait qu'au moment du versement.
     */
    private function totalOrigineCents(Mission $mission): int
    {
        $reservation = $mission->booking;

        $devis = (float) ($reservation?->devis_estime ?? $reservation?->estimated_price ?? 0);

        $cents = (int) round($devis * 100);

        return $cents > 0 ? $cents : (int) ($reservation?->payment_amount_cents ?? 0);
    }
}
