<?php

namespace App\Services\Enterprise;

use App\Models\Booking;
use App\Models\User;
use App\Services\Dispatch\DispatchEngine;
use App\Services\Organizations\OrganizationNotifier;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * L'APPROBATION INTERNE D'UNE ENTREPRISE CLIENTE (E8).
 *
 * CE QUI EXISTAIT ET CE QUI MANQUAIT. Le statut `pending_approval` existait, `BookingHub` savait le
 * poser, et un bouton « Approuver » basculait la réservation en `pending`. Trois choses manquaient,
 * et chacune vide le module de son sens :
 *
 *   1. PERSONNE N'ÉTAIT PRÉVENU. Une demande en attente est invisible tant qu'un responsable
 *      n'ouvre pas l'écran de son propre chef. Le demandeur croit avoir commandé ; l'approbateur ne
 *      sait pas qu'on l'attend ; la découverte se fait le jour prévu de l'intervention.
 *   2. APPROUVER N'ENTRAIT PAS DANS LE DISPATCH. La réservation passait en `pending` et personne ne
 *      cherchait de prestataire. Une approbation qui ne déclenche rien est un tampon sur un
 *      formulaire.
 *   3. RIEN NE DISAIT QUI AVAIT APPROUVÉ. Un contrôle interne dont la décision ne laisse pas de
 *      trace ne sert pas au contrôle interne.
 *
 * ON N'APPROUVE PAS SA PROPRE DEMANDE. Sans cette garde, un demandeur qui possède aussi le droit
 * d'approuver contourne le circuit sans le savoir — et l'entreprise croit avoir un contrôle qu'elle
 * n'a pas.
 *
 * LE REFUS SE CONSERVE, avec son motif. Un refus effacé, c'est la même demande qui revient la
 * semaine suivante sans que personne ne se souvienne pourquoi elle avait été écartée.
 */
class InternalApprovalService
{
    /** Le statut d'une demande qui attend un arbitrage interne. */
    public const STATUT_EN_ATTENTE = 'pending_approval';

    public function __construct(
        protected OrganizationNotifier $notifier,
        protected DispatchEngine $dispatch,
    ) {}

    /**
     * Prévenir ceux qui peuvent trancher.
     *
     * Appelé APRÈS la création : une demande enregistrée puis annoncée vaut mieux qu'une
     * notification envoyée pour une réservation qui n'aboutira pas.
     */
    public function annoncerLaDemande(Booking $booking, ?User $demandeur = null): void
    {
        $organisationId = (int) ($booking->customer_organization_id ?? $booking->organization_account_id ?? 0);

        if ($organisationId <= 0 || $booking->status !== self::STATUT_EN_ATTENTE) {
            return;
        }

        try {
            $this->notifier->notifierPorteursDe(
                organisationId: $organisationId,
                permission: 'bookings.approve',
                titre: 'Demande à approuver',
                corps: sprintf(
                    '%s a demandé une intervention%s.',
                    $demandeur->name ?? 'Un membre',
                    $booking->organizationSite?->name ? ' pour '.$booking->organizationSite->name : '',
                ),
                donnees: ['booking_id' => $booking->id],
                // Le demandeur n'a pas à recevoir sa propre demande : il vient de la faire.
                saufUtilisateurId: $demandeur?->id,
                cleIdempotence: 'approval:requested:'.$booking->id,
            );
        } catch (\Throwable $e) {
            // La demande existe : une notification qui échoue ne doit pas l'effacer.
            report($e);
        }
    }

    /**
     * Approuver — et ENTRER DANS LE DISPATCH.
     *
     * @throws DomainException
     */
    public function approuver(Booking $booking, User $approbateur, ?string $note = null): Booking
    {
        if ($booking->status !== self::STATUT_EN_ATTENTE) {
            throw new DomainException('Cette demande n’attend pas d’approbation.');
        }

        $demandeurId = (int) ($booking->customer_user_id ?? $booking->client_id ?? 0);

        if ($demandeurId === (int) $approbateur->id) {
            /*
             * ON N'APPROUVE PAS SA PROPRE DEMANDE. Sans cette garde, un demandeur qui possède aussi
             * le droit d'approuver contourne le circuit sans le savoir — et l'entreprise croit
             * avoir un contrôle qu'elle n'a pas.
             */
            throw new DomainException('Une demande ne s’approuve pas soi-même.');
        }

        return DB::transaction(function () use ($booking, $approbateur, $note) {
            $booking->forceFill([
                'status' => 'pending',
                // La trace de la décision : un contrôle interne sans trace ne sert pas au
                // contrôle interne.
                'metadata' => array_merge((array) $booking->metadata, [
                    'internal_approval' => [
                        'approved_by' => $approbateur->id,
                        'approved_at' => now()->toIso8601String(),
                        'note' => $note,
                    ],
                ]),
            ])->save();

            /*
             * ENTRER DANS LE DISPATCH — c'est ce qui manquait. Une approbation qui ne déclenche
             * rien laisse la réservation en attente d'un prestataire que personne ne cherche : un
             * tampon sur un formulaire.
             *
             * SOFT-FAIL : la décision d'approbation est prise et enregistrée. Un moteur qui
             * hoquette ne doit pas la faire perdre — la réservation reste `pending` et se
             * redispatche.
             */
            try {
                $this->dispatch->dispatchBooking($booking->fresh());
            } catch (\Throwable $e) {
                report($e);
            }

            $this->prevenirLeDemandeur($booking, 'Demande approuvée', $note);

            return $booking->fresh();
        });
    }

    /** @throws DomainException */
    public function refuser(Booking $booking, User $approbateur, string $motif): Booking
    {
        if ($booking->status !== self::STATUT_EN_ATTENTE) {
            throw new DomainException('Cette demande n’attend pas d’approbation.');
        }

        $booking->forceFill([
            'status' => 'cancelled',
            'metadata' => array_merge((array) $booking->metadata, [
                // Conservé avec son motif : un refus effacé, c'est la même demande qui revient la
                // semaine suivante sans que personne ne se souvienne pourquoi.
                'internal_approval' => [
                    'rejected_by' => $approbateur->id,
                    'rejected_at' => now()->toIso8601String(),
                    'reason' => $motif,
                ],
            ]),
        ])->save();

        $this->prevenirLeDemandeur($booking, 'Demande refusée', $motif);

        return $booking->fresh();
    }

    /**
     * Les demandes en attente d'une société.
     *
     * @return Collection<int, Booking>
     */
    public function enAttente(int $organisationId): Collection
    {
        return Booking::query()
            ->where('customer_organization_id', $organisationId)
            ->where('status', self::STATUT_EN_ATTENTE)
            ->with(['organizationSite:id,name,city', 'clientUser:id,name', 'trade:id,name'])
            ->orderBy('scheduled_at')
            ->get();
    }

    /**
     * Le demandeur apprend la décision.
     *
     * SANS CELA, il croit avoir commandé et découvre le refus le jour prévu — ou n'apprend jamais
     * l'approbation, et rappelle pour savoir où ça en est.
     */
    protected function prevenirLeDemandeur(Booking $booking, string $titre, ?string $note): void
    {
        try {
            $this->notifier->notifierUtilisateur(
                $booking->customer_user_id ?? $booking->client_id,
                $titre,
                $note ?: ($booking->organizationSite->name ?? 'Votre demande a été traitée.'),
                ['booking_id' => $booking->id],
                'approval:decided:'.$booking->id,
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
