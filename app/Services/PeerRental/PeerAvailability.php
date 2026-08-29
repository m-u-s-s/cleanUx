<?php

namespace App\Services\PeerRental;

use App\Models\PeerRental;
use App\Models\PeerVehicle;
use App\Models\PeerVehicleAvailability;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * LE VEHICULE EST-IL LIBRE ?
 *
 * Deux sources se combinent : les periodes que le proprietaire a fermees, et les locations
 * qui bloquent deja les dates. `pending_owner` bloque AUSSI — sans quoi deux locataires
 * retiendraient les memes jours en attendant la meme reponse.
 */
class PeerAvailability
{
    /** Le vehicule est-il libre sur toute la periode ? */
    public function estLibre(
        PeerVehicle $vehicule,
        CarbonInterface $debut,
        CarbonInterface $fin,
        ?int $saufLocationId = null,
    ): bool {
        return $this->motifDIndisponibilite($vehicule, $debut, $fin, $saufLocationId) === null;
    }

    /**
     * POURQUOI IL NE L'EST PAS — pour le dire au locataire, pas seulement le lui refuser.
     *
     * @return string|null null si la periode est libre
     */
    public function motifDIndisponibilite(
        PeerVehicle $vehicule,
        CarbonInterface $debut,
        CarbonInterface $fin,
        ?int $saufLocationId = null,
    ): ?string {
        if ($fin->lessThanOrEqualTo($debut)) {
            return __('La date de retour doit suivre la date de départ.');
        }

        if (! $vehicule->estPubliee()) {
            return __('Ce véhicule n’est pas proposé à la location.');
        }

        $jours = $this->joursEntre($debut, $fin);

        if ($jours < $vehicule->min_rental_days) {
            return __('La durée minimale est de :n jour(s).', ['n' => $vehicule->min_rental_days]);
        }

        if ($vehicule->max_rental_days > 0 && $jours > $vehicule->max_rental_days) {
            return __('La durée maximale est de :n jour(s).', ['n' => $vehicule->max_rental_days]);
        }

        if ($this->chevauche($vehicule, $debut, $fin, $saufLocationId)) {
            return __('Ces dates sont déjà réservées.');
        }

        if ($this->estFerme($vehicule, $debut, $fin)) {
            return __('Le propriétaire a fermé cette période.');
        }

        return null;
    }

    /** Le nombre de jours facturables : toute journee entamee est due. */
    public function joursEntre(CarbonInterface $debut, CarbonInterface $fin): int
    {
        $heures = $debut->diffInMinutes($fin) / 60;

        return max(1, (int) ceil($heures / 24));
    }

    private function chevauche(
        PeerVehicle $vehicule,
        CarbonInterface $debut,
        CarbonInterface $fin,
        ?int $sauf,
    ): bool {
        return PeerRental::query()
            ->where('peer_vehicle_id', $vehicule->id)
            ->quiBloquent()
            ->when($sauf !== null, fn ($q) => $q->whereKeyNot($sauf))
            // Deux periodes se chevauchent des que l'une commence avant que l'autre finisse.
            ->where('starts_at', '<', $fin)
            ->where('ends_at', '>', $debut)
            ->exists();
    }

    /**
     * UNE FERMETURE PEUT ETRE ROUVERTE.
     *
     * Le proprietaire ferme un mois puis rouvre un week-end dedans : la ligne `open` la plus
     * precise l'emporte. Sans cela, il devrait decouper sa fermeture en trois.
     */
    private function estFerme(PeerVehicle $vehicule, CarbonInterface $debut, CarbonInterface $fin): bool
    {
        $periodes = PeerVehicleAvailability::query()
            ->where('peer_vehicle_id', $vehicule->id)
            ->whereDate('starts_on', '<=', $fin->toDateString())
            ->whereDate('ends_on', '>=', $debut->toDateString())
            ->get();

        if ($periodes->isEmpty()) {
            return false;
        }

        $jour = Carbon::parse($debut->toDateString());
        $dernier = Carbon::parse($fin->toDateString());

        while ($jour->lessThanOrEqualTo($dernier)) {
            $couvrantes = $periodes->filter(
                fn (PeerVehicleAvailability $p): bool => $jour->betweenIncluded($p->starts_on, $p->ends_on)
            );

            $ferme = $couvrantes->contains(fn (PeerVehicleAvailability $p): bool => $p->kind === PeerVehicleAvailability::FERMEE);
            $rouvert = $couvrantes->contains(fn (PeerVehicleAvailability $p): bool => $p->kind === PeerVehicleAvailability::OUVERTE);

            if ($ferme && ! $rouvert) {
                return true;
            }

            $jour->addDay();
        }

        return false;
    }

    /**
     * LES JOURS OCCUPES D'UN MOIS, pour peindre le calendrier d'un seul appel.
     *
     * @return list<string> dates au format Y-m-d
     */
    public function joursOccupes(PeerVehicle $vehicule, CarbonInterface $du, CarbonInterface $au): array
    {
        $occupes = [];
        $jour = Carbon::parse($du->toDateString());
        $dernier = Carbon::parse($au->toDateString());

        while ($jour->lessThanOrEqualTo($dernier)) {
            $finDeJournee = (clone $jour)->addDay();

            if ($this->chevauche($vehicule, $jour, $finDeJournee, null) || $this->estFerme($vehicule, $jour, $jour)) {
                $occupes[] = $jour->toDateString();
            }

            $jour->addDay();
        }

        return $occupes;
    }
}
