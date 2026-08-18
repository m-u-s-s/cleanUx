<?php

namespace App\Livewire\Rental;

use App\Services\Rental\RentalAvailability;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * LA CASE « LOCATION » DU CATALOGUE — ET SON ABSENCE QUAND IL N'Y A RIEN À LOUER.
 *
 * ── POURQUOI UN COMPOSANT À PART, ET PAS UN SECTEUR ──────────────────────────────────────────
 *
 * Le carrousel du parcours de commande liste des `Sector`, et chaque secteur mène à des MÉTIERS,
 * puis à des questions, puis à un dispatch de prestataires. La location ne fonctionne pas ainsi :
 * il n'y a pas de professionnel à trouver, c'est le client qui se déplace, et l'objet loué est
 * identifié dès le choix.
 *
 * Créer une ligne `Sector` « Location » aurait envoyé le parcours de commande chercher des métiers
 * qui n'existent pas. Ce composant est donc autonome : il s'insère d'UNE ligne dans la vue du
 * parcours, et `OrderJourney` n'est pas modifié d'un caractère.
 *
 * ── L'ABSENCE EST UNE FONCTIONNALITÉ ─────────────────────────────────────────────────────────
 *
 * Sans voiture disponible, la case ne s'affiche pas. Une porte qui promet un choix derrière une
 * vitrine vide apprend au client que la plateforme annonce ce qu'elle ne sait pas faire — c'est
 * exactement le raisonnement que tient déjà le carrousel des secteurs pour les métiers non
 * servables.
 *
 * Le compte vient de {@see RentalAvailability}, la même source que le catalogue lui-même : deux
 * requêtes distinctes auraient fini par se contredire.
 */
class LocationEntryTile extends Component
{
    public function render(): View
    {
        $disponibles = app(RentalAvailability::class)->combienDeVehiculesProposables();

        return view('livewire.rental.location-entry-tile', [
            'disponibles' => $disponibles,
        ]);
    }
}
