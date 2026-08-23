<?php

namespace App\Services\Missions;

use App\Models\Mission;
use App\Models\MissionFeatureSuspension;
use App\Models\MissionQuoteRevision;
use App\Models\User;
use App\Services\CancellationV2\CancellationEngine;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** LE NOUVEAU DEVIS — proposer, accepter, refuser. */
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
            'evidence_media_ids' => array_map('intval', $mediaIds),
            'status' => MissionQuoteRevision::STATUT_PROPOSEE,
            'window_closes_at' => $etat['closes_at'] !== null
                ? Carbon::parse($etat['closes_at'])
                : Carbon::now()->addMinutes(30),
        ]);
    }

    /** LE CLIENT ACCEPTE — et le contrat est renégocié, pas complété. */
    public function accepter(
        MissionQuoteRevision $revision,
        User $client,
        ?string $paymentMethodId = null,
    ): MissionQuoteRevision {
        if (! $revision->attendLeClient()) {
            throw new DomainException('Cette révision a déjà reçu une réponse.');
        }

        $reservation = $revision->booking;

        if ((int) $reservation?->client_id !== (int) $client->id) {
            throw new DomainException('Cette révision ne vous concerne pas.');
        }

        $complement = app(QuoteRevisionTopUp::class)->autoriser($revision, $paymentMethodId);

        if ($complement['ok'] !== true) {
            $revision->forceFill([
                'status' => MissionQuoteRevision::STATUT_PAIEMENT_ECHOUE,
                'top_up_payment_intent_id' => $complement['intent_id'],
                'last_error' => mb_substr((string) $complement['error'], 0, 1000),
            ])->save();

            // L'EMPREINTE D'ORIGINE N'A PAS ÉTÉ TOUCHÉE : le prestataire garde sa garantie, et le
            // client lit pourquoi son paiement n'est pas passé.
            throw new DomainException(
                'Le complément n’a pas pu être autorisé : '.$complement['error']
            );
        }

        return DB::transaction(function () use ($revision, $reservation, $complement) {
            $instantane = (array) ($reservation->pricing_snapshot ?? []);
            $instantane['quote_revision'] = [
                'revision_id' => $revision->id,
                'original_total_cents' => $revision->original_total_cents,
                'revised_total_cents' => $revision->revised_total_cents,
                'breakdown' => $revision->discount_breakdown,
                'accepted_at' => Carbon::now()->toIso8601String(),
            ];

            $reservation->forceFill([
                'devis_estime' => round($revision->revised_total_cents / 100, 2),
                'estimated_price' => round($revision->revised_total_cents / 100, 2),
                // Ce qui est RÉELLEMENT autorisé : l'empreinte d'origine plus le complément.
                'payment_amount_cents' => $revision->revised_total_cents,
                'pricing_snapshot' => $instantane,
            ])->save();

            $revision->forceFill([
                'status' => MissionQuoteRevision::STATUT_ACCEPTEE,
                'responded_at' => Carbon::now(),
                'client_decision' => null,
                'top_up_payment_intent_id' => $complement['intent_id'],
                'charged_at' => null,
            ])->save();

            $this->arbitrer($revision->fresh());

            return $revision->fresh();
        });
    }

    /** LE CLIENT REFUSE — et c'est LUI qui choisit la suite. */
    public function refuser(
        MissionQuoteRevision $revision,
        User $client,
        string $decision,
    ): MissionQuoteRevision {
        if (! $revision->attendLeClient()) {
            throw new DomainException('Cette révision a déjà reçu une réponse.');
        }

        if ((int) $revision->booking?->client_id !== (int) $client->id) {
            throw new DomainException('Cette révision ne vous concerne pas.');
        }

        if (! in_array($decision, [
            MissionQuoteRevision::DECISION_POURSUIVRE,
            MissionQuoteRevision::DECISION_ARRETER,
        ], true)) {
            throw new DomainException('Dites si l’intervention continue au prix d’origine, ou s’arrête.');
        }

        $revision->forceFill([
            'status' => MissionQuoteRevision::STATUT_REFUSEE,
            'responded_at' => Carbon::now(),
            'client_decision' => $decision,
        ])->save();

        $this->arbitrer($revision->fresh());

        if ($decision === MissionQuoteRevision::DECISION_ARRETER) {
            $this->arreterLIntervention($revision->fresh(), $client);
        }

        return $revision->fresh();
    }

    /** ARRÊTER — par le tuyau commun d'annulation, avec son motif exempté. */
    private function arreterLIntervention(MissionQuoteRevision $revision, User $client): void
    {
        try {
            app(CancellationEngine::class)->execute(
                bookingId: (int) $revision->booking_id,
                actor: $client,
                actorRole: 'client',
                reasonCode: 'quote_revision_declined',
                reasonText: 'Nouveau devis refusé : '.mb_substr($revision->reason_text, 0, 500),
                // IDEMPOTENT SUR LA RÉVISION, pas sur la réservation : deux appels du même écran ne
                // produisent qu'une annulation, et une révision ultérieure garderait la sienne.
                idempotencyKey: 'quote_revision:'.$revision->id,
            );
        } catch (\Throwable $e) {
            report($e);

            $revision->forceFill([
                'last_error' => mb_substr('Arrêt non enregistré : '.$e->getMessage(), 0, 1000),
            ])->save();
        }
    }

    /** ENREGISTRER LE FAIT, PUIS ARBITRER — et jamais l'inverse. SOFT-FAIL DÉLIBÉRÉ. */
    private function arbitrer(MissionQuoteRevision $revision): void
    {
        try {
            $arbitre = app(QuoteRevisionArbiter::class);
            $signal = $arbitre->enregistrer($revision);

            if ($signal !== null) {
                $arbitre->arbitrer($signal);
            }
        } catch (\Throwable $e) {
            report($e);
        }
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

    /** LE TOTAL D'ORIGINE — la même source que la commission, et ce n'est pas un détail. */
    private function totalOrigineCents(Mission $mission): int
    {
        $reservation = $mission->booking;

        // `->` et non `?->` a gauche d'un `??` : l'operateur absorbe deja l'acces sur null.
        $devis = (float) ($reservation->devis_estime ?? $reservation->estimated_price ?? 0);

        $cents = (int) round($devis * 100);

        return $cents > 0 ? $cents : (int) ($reservation->payment_amount_cents ?? 0);
    }
}
