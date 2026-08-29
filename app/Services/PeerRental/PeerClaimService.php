<?php

namespace App\Services\PeerRental;

use App\Models\PeerClaim;
use App\Models\PeerInspection;
use App\Models\PeerRental;
use App\Models\User;
use RuntimeException;

/**
 * LES RETENUES SUR LA CAUTION.
 *
 * Le proprietaire demande, le locataire accepte ou conteste, l'administration tranche. La
 * caution reste bloquee tant qu'une retenue est ouverte : la liberer d'abord reviendrait a
 * donner raison au locataire sans avoir rien examine.
 */
class PeerClaimService
{
    public function __construct(
        private PeerPaymentService $paiement,
        private PeerReturnCharges $supplements,
    ) {}

    /**
     * OUVRIR UNE RETENUE — jamais au-dela de la caution bloquee.
     *
     * @param  list<string>  $preuves  chemins des photos versees au dossier
     */
    public function ouvrir(
        PeerRental $location,
        User $proprietaire,
        string $motif,
        int $montantCents,
        ?string $description = null,
        array $preuves = [],
    ): PeerClaim {
        if ($location->owner_id !== $proprietaire->id) {
            throw new RuntimeException('Seul le propriétaire peut demander une retenue.');
        }

        // LE MOMENT OU L'ON PEUT DEMANDER, C'EST CELUI OU L'ON A REVU LE VEHICULE.
        //
        // Attendre le statut « rendue » serait trop tard : la seconde confirmation de retour
        // libere la caution dans la foulee, et un dommage constate en rendant les cles
        // n'aurait plus rien sur quoi se retenir. L'etat des lieux de RETOUR est la vraie
        // condition — on ne reclame pas un dommage qu'on n'a pas encore pu voir.
        if (! in_array($location->status, [
            PeerRental::STATUT_EN_COURS,
            PeerRental::STATUT_RENDUE,
            PeerRental::STATUT_LITIGE,
        ], true)) {
            throw new RuntimeException('Une retenue ne se demande qu’au retour du véhicule.');
        }

        if ($location->inspection(PeerInspection::PHASE_RETOUR) === null) {
            throw new RuntimeException('L’état des lieux de retour doit être rempli avant toute retenue.');
        }

        $dejaDemande = (int) $location->claims()->whereIn('status', [
            PeerClaim::STATUT_OUVERTE, PeerClaim::STATUT_ACCEPTEE, PeerClaim::STATUT_CONTESTEE,
        ])->sum('amount_cents');

        $plafond = max(0, $location->deposit_cents - $dejaDemande);

        if ($montantCents <= 0) {
            throw new RuntimeException('Le montant demandé doit être positif.');
        }

        if ($montantCents > $plafond) {
            throw new RuntimeException(__('La caution ne couvre plus que :n €.', [
                'n' => number_format($plafond / 100, 2, ',', ' '),
            ]));
        }

        $retenue = PeerClaim::create([
            'peer_rental_id' => $location->id,
            'opened_by' => $proprietaire->id,
            'kind' => $motif,
            'amount_cents' => $montantCents,
            'status' => PeerClaim::STATUT_OUVERTE,
            'description' => $description,
            'evidence' => $preuves === [] ? null : $preuves,
        ]);

        if ($location->status === PeerRental::STATUT_RENDUE) {
            $location->forceFill(['status' => PeerRental::STATUT_LITIGE])->save();
        }

        return $retenue;
    }

    /**
     * LES SUPPLEMENTS SE PROPOSENT SEULS.
     *
     * Kilometres, carburant et retard se mesurent sur les deux etats des lieux : les faire
     * ressaisir a la main inviterait l'erreur, et la contestation avec elle.
     *
     * @return list<PeerClaim>
     */
    public function ouvrirLesSupplementsMesures(PeerRental $location, User $proprietaire): array
    {
        $calcul = $this->supplements->calculer($location);
        $ouvertes = [];

        foreach ($calcul['lignes'] as $ligne) {
            $ouvertes[] = $this->ouvrir(
                $location->refresh(),
                $proprietaire,
                $ligne['cle'],
                $ligne['cents'],
                $ligne['libelle'].' — '.$ligne['detail'],
            );
        }

        return $ouvertes;
    }

    /** LE LOCATAIRE RECONNAIT : la retenue s'applique, sans arbitrage. */
    public function accepter(PeerClaim $retenue, User $locataire): PeerClaim
    {
        if ($retenue->rental->renter_id !== $locataire->id) {
            throw new RuntimeException('Cette retenue ne vous concerne pas.');
        }

        if ($retenue->status !== PeerClaim::STATUT_OUVERTE) {
            throw new RuntimeException('Cette retenue n’est plus en attente.');
        }

        $retenue->forceFill(['status' => PeerClaim::STATUT_ACCEPTEE])->save();

        return $this->solderSiPlusRienNAttend($retenue->refresh());
    }

    /** LE LOCATAIRE CONTESTE : l'administration tranchera, la caution reste bloquee. */
    public function contester(PeerClaim $retenue, User $locataire, ?string $raison = null): PeerClaim
    {
        if ($retenue->rental->renter_id !== $locataire->id) {
            throw new RuntimeException('Cette retenue ne vous concerne pas.');
        }

        if ($retenue->status !== PeerClaim::STATUT_OUVERTE) {
            throw new RuntimeException('Cette retenue n’est plus en attente.');
        }

        $retenue->forceFill([
            'status' => PeerClaim::STATUT_CONTESTEE,
            'resolution_note' => $raison,
        ])->save();

        return $retenue->refresh();
    }

    /** L'ARBITRAGE — le montant accorde peut differer de celui demande. */
    public function arbitrer(PeerClaim $retenue, User $arbitre, int $montantAccordeCents, ?string $note = null): PeerClaim
    {
        if (! $retenue->estEnCours()) {
            throw new RuntimeException('Cette retenue est déjà résolue.');
        }

        $accorde = max(0, min($montantAccordeCents, $retenue->amount_cents));

        $retenue->forceFill([
            'status' => PeerClaim::STATUT_RESOLUE,
            'amount_cents' => $accorde,
            'resolved_by' => $arbitre->id,
            'resolved_at' => now(),
            'resolution_note' => $note,
        ])->save();

        return $this->solderSiPlusRienNAttend($retenue->refresh());
    }

    /** LE PROPRIETAIRE RENONCE. */
    public function retirer(PeerClaim $retenue, User $proprietaire): PeerClaim
    {
        if ($retenue->opened_by !== $proprietaire->id) {
            throw new RuntimeException('Cette retenue n’est pas la vôtre.');
        }

        $retenue->forceFill([
            'status' => PeerClaim::STATUT_ABANDONNEE,
            'amount_cents' => 0,
            'resolved_at' => now(),
        ])->save();

        return $this->solderSiPlusRienNAttend($retenue->refresh());
    }

    /**
     * QUAND PLUS RIEN N'ATTEND, LA CAUTION SE SOLDE EN UNE FOIS.
     *
     * Une capture par retenue prendrait plusieurs fois sur la meme empreinte, ce que Stripe
     * n'autorise pas : on additionne d'abord, on prend ensuite, et le solde retombe.
     */
    private function solderSiPlusRienNAttend(PeerClaim $retenue): PeerClaim
    {
        $location = $retenue->rental->refresh();

        if ($location->claims()->get()->contains(fn (PeerClaim $c): bool => $c->estEnCours())) {
            return $retenue;
        }

        $aRetenir = (int) $location->claims()
            ->whereIn('status', [PeerClaim::STATUT_ACCEPTEE, PeerClaim::STATUT_RESOLUE])
            ->sum('amount_cents');

        if ($aRetenir > 0) {
            $this->paiement->retenirSurLaCaution($location, $aRetenir);
        } else {
            $this->paiement->libererLaCaution($location);
        }

        $location->refresh()->forceFill([
            'status' => PeerRental::STATUT_TERMINEE,
            'extra_charges_cents' => $aRetenir,
        ])->save();

        return $retenue->refresh();
    }
}
