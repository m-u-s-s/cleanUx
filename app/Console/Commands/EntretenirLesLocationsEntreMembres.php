<?php

namespace App\Console\Commands;

use App\Models\PeerRental;
use App\Services\PeerRental\PeerPaymentService;
use App\Services\PeerRental\PeerRentalService;
use App\Services\PeerRental\PeerReviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * LE FILET SOUS LES LOCATIONS ENTRE MEMBRES.
 *
 * Quatre echeances qu'aucun clic ne declenche : l'empreinte qui va tomber, la demande restee
 * sans reponse, l'empreinte deja tombee, et l'avis qui attend son jumeau. Sans cette commande,
 * « les fonds sont bloques jusqu'a la remise » devient faux des la deuxieme semaine.
 */
class EntretenirLesLocationsEntreMembres extends Command
{
    protected $signature = 'peer-rental:entretenir
                            {--limit=200 : Bornes du balayage, pour ne pas monopoliser un worker}';

    protected $description = 'Réautorise les empreintes qui expirent, solde les demandes sans réponse et révèle les avis en attente.';

    public function handle(
        PeerPaymentService $paiement,
        PeerRentalService $locations,
        PeerReviewService $avis,
    ): int {
        $limite = max(1, (int) $this->option('limit'));

        $reautorisees = $this->reautoriserAvantLEcheance($paiement, $limite);
        $expirees = $this->solderLesDemandesSansReponse($locations, $limite);
        $tombees = $this->marquerLesEmpreintesTombees($paiement, $limite);
        $reveles = $avis->revelerLesAvisEnAttente();

        $this->info("Réautorisées : {$reautorisees} · Demandes expirées : {$expirees} · Empreintes tombées : {$tombees} · Avis révélés : {$reveles}");

        return self::SUCCESS;
    }

    /**
     * L'EMPREINTE SE REPOSE AVANT DE TOMBER.
     *
     * Stripe ne garde une autorisation que sept jours. On la refait a l'approche de la
     * remise — et seulement pour les locations qui iront jusque-la.
     */
    private function reautoriserAvantLEcheance(PeerPaymentService $paiement, int $limite): int
    {
        $marge = (int) config('peer_rental.reauthorize_hours_before', 24);

        $aRefaire = PeerRental::query()
            ->whereIn('status', [PeerRental::STATUT_EN_ATTENTE, PeerRental::STATUT_CONFIRMEE])
            ->where('payment_status', PeerRental::PAIEMENT_AUTORISE)
            ->whereNotNull('payment_authorized_until')
            ->where('payment_authorized_until', '<=', now()->addHours($marge))
            // Une empreinte deja tombee ne se refait pas ici : elle passe par le troisieme
            // balayage, qui la signale au lieu de la maquiller.
            ->where('payment_authorized_until', '>', now())
            ->orderBy('payment_authorized_until')
            ->limit($limite)
            ->get();

        $faites = 0;

        foreach ($aRefaire as $location) {
            try {
                if ($paiement->reautoriserLeLoyer($location) !== null) {
                    $faites++;
                }
            } catch (\Throwable $e) {
                report($e);
                Log::warning('Réautorisation impossible', [
                    'peer_rental' => $location->reference,
                    'erreur' => $e->getMessage(),
                ]);
            }
        }

        return $faites;
    }

    /** LE PROPRIETAIRE N'A PAS REPONDU : les fonds du locataire lui reviennent. */
    private function solderLesDemandesSansReponse(PeerRentalService $locations, int $limite): int
    {
        $delai = (int) config('peer_rental.owner_response_hours', 24);

        $oubliees = PeerRental::query()
            ->where('status', PeerRental::STATUT_EN_ATTENTE)
            ->where('created_at', '<=', now()->subHours($delai))
            ->orderBy('created_at')
            ->limit($limite)
            ->get();

        $soldees = 0;

        foreach ($oubliees as $location) {
            try {
                app(PeerPaymentService::class)->solderALAnnulation($location, 0);

                $location->forceFill([
                    'status' => PeerRental::STATUT_EXPIREE,
                    'cancelled_at' => now(),
                    'cancellation_reason' => __('Le propriétaire n’a pas répondu à temps.'),
                ])->save();

                $soldees++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $soldees;
    }

    /**
     * L'EMPREINTE EST TOMBEE SANS AVOIR ETE CAPTUREE.
     *
     * On le DIT plutot que de laisser croire que l'argent est encore la : une remise faite
     * sur une empreinte morte ne capturerait rien, et le proprietaire l'apprendrait apres.
     */
    private function marquerLesEmpreintesTombees(PeerPaymentService $paiement, int $limite): int
    {
        $tombees = PeerRental::query()
            ->whereIn('status', [PeerRental::STATUT_EN_ATTENTE, PeerRental::STATUT_CONFIRMEE])
            ->where('payment_status', PeerRental::PAIEMENT_AUTORISE)
            ->whereNotNull('payment_authorized_until')
            ->where('payment_authorized_until', '<=', now())
            ->limit($limite)
            ->get();

        foreach ($tombees as $location) {
            $paiement->marquerExpire($location);

            Log::warning('Empreinte de location tombée avant la remise', [
                'peer_rental' => $location->reference,
            ]);
        }

        return $tombees->count();
    }
}
