<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PricingPageController extends Controller
{
    public function __invoke(): View
    {
        $tiers = config('premium.tiers', []);

        return view('pages.pricing', [
            'tiers' => $tiers,
            'seoTitle' => 'Tarifs — Brio',
            'seoDescription' => 'Découvrez nos formules Gratuit, Pro et Business. Réservez des services professionnels en Belgique à partir de 0€/mois.',
        ]);
    }
}
