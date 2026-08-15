<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    /**
     * @param  bool  $cta  Afficher le bouton flottant « Réserver maintenant ».
     *
     * LE DÉFAUT QU'IL FERME : cette mise en page sert DEUX familles de pages qui n'ont rien à voir.
     * D'un côté la vitrine, où l'on flâne et où un bouton de réservation permanent est utile ; de
     * l'autre les formulaires d'authentification, où le visiteur est déjà en train de faire quelque
     * chose. Sur l'inscription prestataire, le bouton flottant se posait par-dessus le formulaire —
     * il recouvrait des champs, et invitait à partir réserver une prestation quelqu'un qui était en
     * train de s'inscrire pour EN RENDRE.
     *
     * LE DÉFAUT EST À FAUX plutôt qu'à vrai, et c'est le cœur de la correction : une page ajoutée
     * demain sans y penser n'aura pas de bouton — un manque discret. L'inverse la ferait recouvrir
     * son propre formulaire, ce qu'on ne découvre qu'en s'inscrivant à la main.
     */
    public function __construct(public bool $cta = false) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
