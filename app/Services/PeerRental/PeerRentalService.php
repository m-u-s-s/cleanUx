<?php

namespace App\Services\PeerRental;

use App\Models\PeerCode;
use App\Models\PeerInspection;
use App\Models\PeerRental;
use App\Models\PeerVehicle;
use App\Models\User;
use App\Services\PeerRental\Contracts\Louable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * LE PARCOURS D'UNE LOCATION ENTRE MEMBRES.
 *
 * Une seule regle gouverne l'argent : rien n'est encaisse tant que LES DEUX parties n'ont
 * pas confirme la remise des cles. Le reste — acceptation, annulation, retour — s'y rattache.
 */
class PeerRentalService
{
    public function __construct(
        private PeerAvailability $disponibilite,
        private PeerPricing $tarification,
        private PeerPaymentService $paiement,
        private PeerDriverEligibility $eligibilite,
    ) {}

    /**
     * UNE DEMANDE DE LOCATION, FONDS BLOQUES.
     *
     * L'empreinte est posee AVANT que le proprietaire reponde : une demande sans provision
     * lui ferait bloquer ses dates pour rien.
     *
     * @param  array{livraison?: bool, assurance?: string|null, adresse_livraison?: string|null}  $options
     *
     * @throws ValidationException
     */
    public function demander(
        Louable&Model $bien,
        User $locataire,
        CarbonInterface $debut,
        CarbonInterface $fin,
        string $paymentMethodId,
        array $options = [],
    ): PeerRental {
        // L'ELIGIBILITE DU CONDUCTEUR NE VAUT QUE POUR UN VEHICULE : personne n'exige un
        // permis pour dormir dans un studio. Elle ne s'applique donc qu'a ce qu'elle concerne.
        $refus = $bien instanceof PeerVehicle
            ? $this->eligibilite->motifDeRefus($locataire, $bien)
            : null;

        if ($refus !== null) {
            throw ValidationException::withMessages(['locataire' => $refus]);
        }

        $indisponible = $this->disponibilite->motifDIndisponibilite($bien, $debut, $fin);

        if ($indisponible !== null) {
            throw ValidationException::withMessages(['dates' => $indisponible]);
        }

        $devis = $this->tarification->devis($bien, $debut, $fin, $options);

        $location = DB::transaction(function () use ($bien, $locataire, $debut, $fin, $devis, $options): PeerRental {
            // LA DERNIERE VERIFICATION SE FAIT DANS LA TRANSACTION : entre le devis et
            // l'ecriture, une autre demande a pu prendre les memes dates.
            if (! $this->disponibilite->estLibre($bien, $debut, $fin)) {
                throw ValidationException::withMessages(['dates' => __('Ces dates viennent d’être réservées.')]);
            }

            return PeerRental::create([
                'reference' => PeerRental::genererUneReference(),
                // LES DEUX COLONNES : la polymorphe pour la couche partagee, l'ancienne pour
                // tout le module vehicules qui la lit encore.
                'rentable_type' => $bien->getMorphClass(),
                'rentable_id' => $bien->getKey(),
                'peer_vehicle_id' => $bien instanceof PeerVehicle ? $bien->id : null,
                'owner_id' => $bien->proprietaire()?->id,
                'renter_id' => $locataire->id,
                'status' => PeerRental::STATUT_EN_ATTENTE,
                'starts_at' => $debut,
                'ends_at' => $fin,
                'days' => $devis['days'],
                'delivery_requested' => (bool) ($options['livraison'] ?? false),
                'delivery_address' => $options['adresse_livraison'] ?? null,
                'daily_price_cents' => $devis['daily_price_cents'],
                'subtotal_cents' => $devis['subtotal_cents'],
                'discount_cents' => $devis['discount_cents'],
                'delivery_cents' => $devis['delivery_cents'],
                'insurance_cents' => $devis['insurance_cents'],
                'total_cents' => $devis['total_cents'],
                'currency' => $devis['currency'],
                'platform_fee_cents' => $devis['platform_fee_cents'],
                'owner_payout_cents' => $devis['owner_payout_cents'],
                'commission_rate' => $devis['commission_rate'],
                'deposit_cents' => $devis['deposit_cents'],
                'included_km' => $devis['included_km'],
                'extra_km_price_cents' => $bien instanceof PeerVehicle ? $bien->extra_km_price_cents : 0,
                'insurance_plan_key' => $options['assurance'] ?? null,
                'metadata' => ['devis' => $devis],
            ]);
        });

        $this->paiement->autoriserLeLoyer($location, $paymentMethodId);

        // LA RESERVATION INSTANTANEE N'ATTEND PAS : le proprietaire l'a decidee a l'avance.
        if ($bien->reservationInstantanee()) {
            $this->accepter($location->refresh(), $bien->proprietaire());
        }

        return $location->refresh();
    }

    /** LE PROPRIETAIRE ACCEPTE : le code de remise nait ici, pas avant. */
    public function accepter(PeerRental $location, ?User $proprietaire = null): PeerRental
    {
        $this->exigerLeProprietaire($location, $proprietaire);

        if ($location->status !== PeerRental::STATUT_EN_ATTENTE) {
            throw new RuntimeException('Cette demande n’est plus en attente.');
        }

        $location->forceFill([
            'status' => PeerRental::STATUT_CONFIRMEE,
            'accepted_at' => now(),
        ])->save();

        $this->genererLeCode($location, PeerCode::PHASE_REMISE);

        return $location->refresh();
    }

    /** LE PROPRIETAIRE REFUSE : les fonds sont rendus tout de suite. */
    public function refuser(PeerRental $location, ?User $proprietaire = null, ?string $motif = null): PeerRental
    {
        $this->exigerLeProprietaire($location, $proprietaire);

        if ($location->status !== PeerRental::STATUT_EN_ATTENTE) {
            throw new RuntimeException('Cette demande n’est plus en attente.');
        }

        $this->paiement->solderALAnnulation($location, 0);

        $location->forceFill([
            'status' => PeerRental::STATUT_REFUSEE,
            'declined_at' => now(),
            'cancellation_reason' => $motif,
        ])->save();

        return $location->refresh();
    }

    /**
     * L'ANNULATION PAR LE LOCATAIRE — le bareme de l'annonce dit ce qui reste du.
     */
    public function annulerParLeLocataire(PeerRental $location, User $locataire, ?string $motif = null): PeerRental
    {
        if ($location->renter_id !== $locataire->id) {
            throw new RuntimeException('Cette location n’est pas la vôtre.');
        }

        if (! in_array($location->status, [PeerRental::STATUT_EN_ATTENTE, PeerRental::STATUT_CONFIRMEE], true)) {
            throw new RuntimeException('Une location déjà remise ne s’annule plus.');
        }

        $frais = $this->fraisDAnnulation($location);

        $this->paiement->solderALAnnulation($location, $frais);

        $location->forceFill([
            'status' => PeerRental::STATUT_ANNULEE,
            'cancelled_at' => now(),
            'cancelled_by' => $locataire->id,
            'cancellation_fee_cents' => $frais,
            'cancellation_reason' => $motif,
        ])->save();

        return $location->refresh();
    }

    /**
     * LE DESISTEMENT DU PROPRIETAIRE — ce n'est PAS une annulation de locataire.
     *
     * Le locataire n'a rien fait : il est rembourse en entier, et le proprietaire porte une
     * penalite. Confondre les deux ferait payer le mauvais.
     */
    public function seDesister(PeerRental $location, User $proprietaire, ?string $motif = null): PeerRental
    {
        $this->exigerLeProprietaire($location, $proprietaire);

        if (! in_array($location->status, [PeerRental::STATUT_EN_ATTENTE, PeerRental::STATUT_CONFIRMEE], true)) {
            throw new RuntimeException('Une location déjà remise ne s’annule plus.');
        }

        $this->paiement->solderALAnnulation($location, 0);

        $penalite = (int) round(
            $location->owner_payout_cents * max(0, (int) config('peer_rental.owner_withdrawal_penalty_percent', 20)) / 100
        );

        $location->forceFill([
            'status' => PeerRental::STATUT_ANNULEE,
            'cancelled_at' => now(),
            'cancelled_by' => $proprietaire->id,
            'cancellation_reason' => $motif,
            'metadata' => array_merge($location->metadata ?? [], [
                'desistement_proprietaire' => [
                    'a' => now()->toIso8601String(),
                    'penalite_cents' => $penalite,
                ],
            ]),
        ])->save();

        return $location->refresh();
    }

    /** Ce que le bareme de l'annonce retient, en centimes. */
    public function fraisDAnnulation(PeerRental $location): int
    {
        // LE BAREME VIENT DU BIEN, QUEL QU IL SOIT. Lire `vehicle` ici faisait planter
        // l annulation d un sejour - et l annulation est le moment ou l argent bouge.
        $politique = $location->bien()?->politiqueDAnnulation() ?? '';

        /** @var list<array{heures: int, retenue_percent: int}> $paliers */
        $paliers = config('peer_rental.cancellation.'.$politique, []);

        // UN BAREME INCONNU NE VAUT PAS « ON GARDE TOUT ». Sans paliers, la boucle ci-dessous
        // tombe sur la retenue totale : une faute de frappe couterait au locataire son loyer
        // entier. Le bareme median s'applique a la place.
        if ($paliers === []) {
            /** @var list<array{heures: int, retenue_percent: int}> $paliers */
            $paliers = config('peer_rental.cancellation.moderee', []);
        }

        $heuresAvant = now()->diffInHours($location->starts_at, false);

        foreach ($paliers as $palier) {
            if ($heuresAvant >= $palier['heures']) {
                return (int) round($location->total_cents * $palier['retenue_percent'] / 100);
            }
        }

        return $location->total_cents;
    }

    /**
     * LA REMISE DES CLES — UNE SIGNATURE, PUIS L'AUTRE.
     *
     * Le proprietaire saisit le code que le locataire lui montre ; chacun signe l'etat des
     * lieux de depart. C'est la SECONDE confirmation qui declenche la capture : la premiere
     * ne prend rien, et une seule ne suffira jamais.
     */
    public function confirmerLaRemise(PeerRental $location, User $acteur, ?string $code = null): PeerRental
    {
        if ($location->status !== PeerRental::STATUT_CONFIRMEE) {
            throw new RuntimeException('Cette location n’est pas prête pour la remise.');
        }

        $etatDesLieux = $location->inspection(PeerInspection::PHASE_DEPART);

        if ($etatDesLieux === null) {
            throw new RuntimeException('L’état des lieux de départ doit être rempli avant la remise.');
        }

        $manquants = $etatDesLieux->anglesManquants();

        if ($manquants !== []) {
            throw new RuntimeException('Photos manquantes : '.implode(', ', $manquants));
        }

        $estProprietaire = $acteur->id === $location->owner_id;

        // LE CODE N'EST DEMANDE QU'AU PROPRIETAIRE : c'est le locataire qui l'affiche.
        if ($estProprietaire) {
            $this->consommerLeCode($location, PeerCode::PHASE_REMISE, (string) $code);
        } elseif ($acteur->id !== $location->renter_id) {
            throw new RuntimeException('Vous n’êtes pas partie à cette location.');
        }

        $location->forceFill($estProprietaire
            ? ['handover_owner_confirmed_at' => now()]
            : ['handover_renter_confirmed_at' => now()])->save();

        $etatDesLieux->forceFill($estProprietaire
            ? ['owner_signed_at' => now()]
            : ['renter_signed_at' => now()])->save();

        $location->refresh();

        if (! $location->remiseConfirmeeParLesDeux()) {
            return $location;
        }

        // LES DEUX ONT CONFIRME. C'est ici, et nulle part ailleurs, que l'argent bouge.
        $this->paiement->capturerLeLoyer($location);
        $this->paiement->autoriserLaCaution($location);

        $location->forceFill([
            'status' => PeerRental::STATUT_EN_COURS,
            'handed_over_at' => now(),
        ])->save();

        $this->genererLeCode($location, PeerCode::PHASE_RETOUR);

        return $location->refresh();
    }

    /**
     * LE RETOUR — meme regle, deux signatures.
     *
     * Sans retenue demandee, la caution est liberee et la location se termine. Avec, elle
     * reste bloquee : la liberer d'abord reviendrait a trancher en faveur du locataire.
     */
    public function confirmerLeRetour(PeerRental $location, User $acteur, ?string $code = null): PeerRental
    {
        if ($location->status !== PeerRental::STATUT_EN_COURS) {
            throw new RuntimeException('Cette location n’est pas en cours.');
        }

        $etatDesLieux = $location->inspection(PeerInspection::PHASE_RETOUR);

        if ($etatDesLieux === null) {
            throw new RuntimeException('L’état des lieux de retour doit être rempli.');
        }

        $manquants = $etatDesLieux->anglesManquants();

        if ($manquants !== []) {
            throw new RuntimeException('Photos manquantes : '.implode(', ', $manquants));
        }

        $estProprietaire = $acteur->id === $location->owner_id;

        if ($estProprietaire) {
            $this->consommerLeCode($location, PeerCode::PHASE_RETOUR, (string) $code);
        } elseif ($acteur->id !== $location->renter_id) {
            throw new RuntimeException('Vous n’êtes pas partie à cette location.');
        }

        $location->forceFill($estProprietaire
            ? ['return_owner_confirmed_at' => now()]
            : ['return_renter_confirmed_at' => now()])->save();

        $etatDesLieux->forceFill($estProprietaire
            ? ['owner_signed_at' => now()]
            : ['renter_signed_at' => now()])->save();

        $location->refresh();

        if (! $location->retourConfirmeParLesDeux()) {
            return $location;
        }

        $location->forceFill([
            'status' => PeerRental::STATUT_RENDUE,
            'returned_at' => now(),
        ])->save();

        return $this->cloturerSiRienNeReste($location->refresh());
    }

    /** Sans retenue en cours, la caution retombe et la location se termine. */
    public function cloturerSiRienNeReste(PeerRental $location): PeerRental
    {
        if ($location->status !== PeerRental::STATUT_RENDUE) {
            return $location;
        }

        $enCours = $location->claims()->get()->contains(fn ($retenue): bool => $retenue->estEnCours());

        if ($enCours) {
            $location->forceFill(['status' => PeerRental::STATUT_LITIGE])->save();

            return $location->refresh();
        }

        $this->paiement->libererLaCaution($location);

        $location->forceFill(['status' => PeerRental::STATUT_TERMINEE])->save();

        return $location->refresh();
    }

    /**
     * LE CODE A SIX CHIFFRES, RENDU EN CLAIR UNE SEULE FOIS.
     *
     * Il n'est stocke que sous forme d'empreinte : personne, pas meme l'administration, ne
     * peut le relire ensuite.
     */
    public function genererLeCode(PeerRental $location, string $phase): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PeerCode::query()
            ->where('peer_rental_id', $location->id)
            ->where('phase', $phase)
            ->whereNull('consumed_at')
            ->delete();

        PeerCode::create([
            'peer_rental_id' => $location->id,
            'phase' => $phase,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addHours((int) config('peer_rental.code_ttl_hours', 12)),
        ]);

        return $code;
    }

    private function consommerLeCode(PeerRental $location, string $phase, string $saisi): void
    {
        $code = PeerCode::query()
            ->where('peer_rental_id', $location->id)
            ->where('phase', $phase)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if ($code === null || ! $code->estUtilisable()) {
            throw new RuntimeException('Ce code n’est plus valable. Demandez-en un nouveau.');
        }

        if (! $code->correspond($saisi)) {
            $code->increment('attempts');

            throw new RuntimeException('Code incorrect.');
        }

        $code->forceFill(['consumed_at' => now()])->save();
    }

    private function exigerLeProprietaire(PeerRental $location, ?User $proprietaire): void
    {
        if ($proprietaire !== null && $location->owner_id !== $proprietaire->id) {
            throw new RuntimeException('Ce véhicule n’est pas le vôtre.');
        }
    }
}
