<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class GuestLayout extends Component
{
    /**
     * LE DÉFAUT QU'IL FERME : cette mise en page sert DEUX familles de pages qui n'ont rien à voir.
     *
     * @param  bool  $cta  Afficher le bouton flottant « Réserver maintenant ».
     */
    public function __construct(public bool $cta = false) {}

    /** Get the view / contents that represents the component. */
    public function render(): View
    {
        return view('layouts.guest');
    }
}
