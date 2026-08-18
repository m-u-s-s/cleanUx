<?php

namespace App\Livewire\Rental;

use App\Models\RentalVehicle;
use App\Services\Rental\RentalAvailability;
use App\Services\Rental\RentalBookingService;
use App\Services\Rental\RentalPricing;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * LA FICHE D'UNE VOITURE, ET LE FORMULAIRE DE LOCATION.
 *
 * ── LES QUESTIONS SONT CELLES D'UNE AGENCE ───────────────────────────────────────────────────
 *
 * Dates et heures de départ et de retour, agence de retrait, conducteur, permis, et le choix de la
 * garantie. Ce sont exactement les informations qu'un comptoir demande, et chacune sert :
 *
 *   LES DATES        décident du prix, de la disponibilité, et de rien d'autre
 *   LA NAISSANCE     l'âge minimum du véhicule s'apprécie AU JOUR DU DÉPART, pas aujourd'hui : un
 *                    client de vingt ans qui réserve pour dans six mois aura l'âge au volant
 *   LE PERMIS        même raison pour son ancienneté ; le numéro sert au comptoir, pas au calcul
 *   LA GARANTIE      elle ne s'impose jamais : le client voit les deux prix et tranche
 *
 * ── LE PRIX AVANT L'IDENTITÉ ─────────────────────────────────────────────────────────────────
 *
 * Comme dans le parcours de commande, rien ici ne réclame de compte. Le devis s'affiche et se
 * recalcule à chaque changement de date ; on ne demande le conducteur qu'au moment de réserver.
 */
#[Layout('layouts.app')]
class LocationVehicle extends Component
{
    #[Locked]
    public int $vehicleId;

    public string $debut = '';

    public string $fin = '';

    public string $protection = RentalVehicle::PROTECTION_SANS;

    public string $driverFirstName = '';

    public string $driverLastName = '';

    public string $driverBirthdate = '';

    public string $driverEmail = '';

    public string $driverPhone = '';

    public string $licenseNumber = '';

    public string $licenseCountry = 'BE';

    public string $licenseIssuedAt = '';

    public ?string $erreur = null;

    public function mount(RentalVehicle $vehicle): void
    {
        /*
         * UNE VOITURE HORS VITRINE N'A PAS DE FICHE PUBLIQUE.
         *
         * `is_active` décide seul de la présence au catalogue ; laisser son URL ouverte
         * permettrait de réserver un véhicule que l'administrateur a justement retiré — en tapant
         * l'adresse, ou depuis un lien partagé la veille.
         */
        abort_unless($vehicle->is_active, 404);

        $this->vehicleId = $vehicle->id;

        // Deux jours à partir de demain : une proposition de départ, que le client change. Un
        // formulaire vide obligerait à saisir avant de voir le moindre prix.
        $this->debut = now()->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
        $this->fin = now()->addDays(3)->setTime(9, 0)->format('Y-m-d\TH:i');
    }

    public function reserver(): void
    {
        $this->erreur = null;

        $valide = $this->validate([
            'debut' => ['required', 'date', 'after:now'],
            'fin' => ['required', 'date', 'after:debut'],
            'protection' => ['required', 'in:'.RentalVehicle::PROTECTION_SANS.','.RentalVehicle::PROTECTION_AVEC],
            'driverFirstName' => ['required', 'string', 'max:60'],
            'driverLastName' => ['required', 'string', 'max:60'],
            'driverBirthdate' => ['required', 'date', 'before:today'],
            'driverEmail' => ['required', 'email', 'max:180'],
            'driverPhone' => ['nullable', 'string', 'max:32'],
            'licenseNumber' => ['required', 'string', 'max:40'],
            'licenseCountry' => ['required', 'string', 'size:2'],
            'licenseIssuedAt' => ['required', 'date', 'before:today'],
        ], [], [
            'debut' => 'date de départ',
            'fin' => 'date de retour',
            'driverFirstName' => 'prénom du conducteur',
            'driverLastName' => 'nom du conducteur',
            'driverBirthdate' => 'date de naissance',
            'licenseNumber' => 'numéro de permis',
            'licenseIssuedAt' => 'date d’obtention du permis',
        ]);

        $vehicule = $this->vehicule();
        $debut = Carbon::parse($valide['debut']);
        $fin = Carbon::parse($valide['fin']);

        /*
         * LA DISPONIBILITÉ EST REVÉRIFIÉE ICI AUSSI.
         *
         * Le service la revérifie dans sa transaction — c'est lui qui protège vraiment. Ce
         * contrôle-ci sert à donner au client un message dans SON formulaire plutôt qu'une
         * exception après coup, sur une page qu'il n'a pas demandée.
         */
        if (! app(RentalAvailability::class)->estLibre($vehicule, $debut, $fin)) {
            $this->erreur = 'Ce véhicule n’est plus disponible sur ces dates. Essayez d’autres dates.';

            return;
        }

        $location = app(RentalBookingService::class)->preparer($vehicule, [
            'starts_at' => $debut,
            'ends_at' => $fin,
            'protection' => $valide['protection'],
            'driver_first_name' => $valide['driverFirstName'],
            'driver_last_name' => $valide['driverLastName'],
            'driver_birthdate' => $valide['driverBirthdate'],
            'driver_email' => $valide['driverEmail'],
            'driver_phone' => $valide['driverPhone'] ?? null,
            'license_number' => $valide['licenseNumber'],
            'license_country' => strtoupper($valide['licenseCountry']),
            'license_issued_at' => $valide['licenseIssuedAt'],
        ], $this->jetonDeSession());

        $this->redirectRoute('location.recapitulatif', ['reference' => $location->reference], navigate: true);
    }

    public function render(): View
    {
        $vehicule = $this->vehicule();

        return view('livewire.rental.location-vehicle', [
            'vehicule' => $vehicule,
            'devis' => app(RentalPricing::class)->devis($vehicule, $this->dateOuNull($this->debut), $this->dateOuNull($this->fin)),
        ]);
    }

    private function vehicule(): RentalVehicle
    {
        return RentalVehicle::query()
            ->with(['galerie', 'rotation360', 'modele3d', 'pickupPoint'])
            ->findOrFail($this->vehicleId);
    }

    private function dateOuNull(string $valeur): ?Carbon
    {
        if ($valeur === '') {
            return null;
        }

        try {
            return Carbon::parse($valeur);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Le jeton qui rattache un panier de location à un visiteur sans compte.
     *
     * Même principe que le parcours de commande : on ne demande pas d'identité pour voir un prix.
     * Le jeton vit dans la session ; il permet de retrouver sa réservation en revenant.
     */
    private function jetonDeSession(): string
    {
        $jeton = session()->get('rental_session_token');

        if (! is_string($jeton) || $jeton === '') {
            $jeton = (string) Str::uuid();
            session()->put('rental_session_token', $jeton);
        }

        return $jeton;
    }
}
