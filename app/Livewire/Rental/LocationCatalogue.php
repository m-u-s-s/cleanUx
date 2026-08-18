<?php

namespace App\Livewire\Rental;

use App\Services\Rental\RentalAvailability;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * LA VITRINE : TOUTES NOS VOITURES, AVEC DE QUOI TRIER.
 *
 * ── CE CATALOGUE NE RESSEMBLE PAS À L'AUTRE, ET C'EST VOULU ──────────────────────────────────
 *
 * Le parcours de commande va du SECTEUR au MÉTIER puis aux QUESTIONS, et cherche ensuite un
 * professionnel disponible. Ici, l'objet est visible dès la première seconde : on choisit une
 * voiture précise, comme dans n'importe quelle agence. Aucun dispatch, aucun prestataire, aucune
 * zone de service — le client vient chercher le véhicule là où il est.
 *
 * Les deux parcours ne partagent aucun composant. C'est ce qui permet de faire évoluer l'un sans
 * risquer l'autre.
 *
 * ── LES DATES D'ABORD, PARCE QU'ELLES CHANGENT LA VITRINE ────────────────────────────────────
 *
 * Une voiture louée sur la période demandée ne doit pas s'afficher. Tant que le client n'a pas
 * choisi ses dates, on montre ce qui est physiquement là aujourd'hui — une vitrine vide par excès
 * de prudence serait pire qu'une liste à affiner.
 *
 * ── LES FILTRES SORTENT DU PARC, PAS D'UNE LISTE FIGÉE ───────────────────────────────────────
 *
 * Proposer « monospace » sur un parc qui n'en a aucun apprend au client que la vitrine ment. Les
 * options viennent donc des voitures réellement proposables.
 */
#[Layout('layouts.app')]
class LocationCatalogue extends Component
{
    #[Url(as: 'du', except: '')]
    public string $debut = '';

    #[Url(as: 'au', except: '')]
    public string $fin = '';

    #[Url(as: 'cat', except: '')]
    public string $categorie = '';

    #[Url(as: 'boite', except: '')]
    public string $transmission = '';

    #[Url(as: 'energie', except: '')]
    public string $carburant = '';

    #[Url(as: 'places', except: '')]
    public string $placesMin = '';

    #[Url(as: 'max', except: '')]
    public string $prixMax = '';

    public function reinitialiserLesFiltres(): void
    {
        $this->categorie = '';
        $this->transmission = '';
        $this->carburant = '';
        $this->placesMin = '';
        $this->prixMax = '';
    }

    public function render(): View
    {
        [$debut, $fin] = $this->periode();
        $service = app(RentalAvailability::class);

        return view('livewire.rental.location-catalogue', [
            'vehicules' => $service->catalogue($debut, $fin, [
                'category' => $this->categorie ?: null,
                'transmission' => $this->transmission ?: null,
                'fuel' => $this->carburant ?: null,
                'seats_min' => $this->placesMin !== '' ? (int) $this->placesMin : null,
                // Le plafond est saisi en unités et vit en centimes : la conversion se fait ici,
                // une seule fois, plutôt que dans le service qui ne connaît que des centimes.
                'price_max_cents' => $this->prixMax !== '' ? (int) round(((float) $this->prixMax) * 100) : null,
            ]),
            'options' => $service->optionsDeFiltre($debut, $fin),
            'debutChoisi' => $debut,
            'finChoisie' => $fin,
        ]);
    }

    /**
     * Les dates demandées, ou rien.
     *
     * UNE DATE SEULE NE VAUT PAS UNE PÉRIODE. Un client qui a saisi le départ sans le retour n'a
     * pas encore dit ce qu'il veut ; deviner « une journée » afficherait une disponibilité qu'il
     * n'a pas demandée. On rend donc `null` tant que les deux ne sont pas là, et le service montre
     * ce qui est présent aujourd'hui.
     *
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function periode(): array
    {
        if ($this->debut === '' || $this->fin === '') {
            return [null, null];
        }

        try {
            $debut = Carbon::parse($this->debut);
            $fin = Carbon::parse($this->fin);
        } catch (\Throwable) {
            // Une date illisible vient de l'URL, que n'importe qui peut écrire. On l'ignore plutôt
            // que de lever : la vitrine doit s'afficher, quitte à ne pas filtrer.
            return [null, null];
        }

        return $fin->greaterThan($debut) ? [$debut, $fin] : [null, null];
    }
}
