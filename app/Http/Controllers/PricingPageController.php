<?php

namespace App\Http\Controllers;

class PricingPageController extends Controller
{
    public function __invoke(): \Illuminate\View\View
    {
        $tiers = config('premium.tiers', []);

        return view('pages.pricing', [
            'tiers'          => $tiers,
            'seoTitle'       => 'Tarifs — CleanUx',
            'seoDescription' => 'Découvrez nos formules Gratuit, Pro et Business. Réservez des services professionnels en Belgique à partir de 0€/mois.',
        ]);
    }
}
