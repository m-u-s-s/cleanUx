<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ServiceZone;
use App\Models\Trade;
use App\Services\Localization\Money;
use Illuminate\Contracts\View\View;

final class ServicePageController extends Controller
{
    public function index(): View
    {
        $trades = Trade::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('pages.services-index', compact('trades'));
    }

    public function show(string $tradeSlug, ?string $city = null): View
    {
        $trade = Trade::where(function ($q) use ($tradeSlug) {
            $q->where('slug', $tradeSlug)
                ->orWhere('code', $tradeSlug);
        })
            ->where('is_active', true)
            ->firstOrFail();

        $zones = $city
            ? collect()
            : ServiceZone::where('is_bookable', true)->orderBy('name')->get();

        $cityLabel = $city ? ucfirst(str_replace('-', ' ', $city)) : null;
        $seoTitle = $trade->name.($cityLabel ? ' a '.$cityLabel : '').' — Brio';
        $seoDescription = 'Reservez un '.strtolower($trade->name).' verifie'
            .($cityLabel ? ' a '.$cityLabel : ' en Belgique')
            .'. Paiement securise, suivi en temps reel. Devis gratuit en 2 minutes.';

        /*
         * LA DEVISE DES DONNEES STRUCTUREES, plutot qu'un « EUR » ecrit deux fois dans la vue.
         *
         * Cette page est PUBLIQUE : son `priceCurrency` part chez les moteurs de recherche.
         * Un metier annonce en euros sur un marche qui facture en dirhams n'est pas une
         * coquetterie de balisage — c'est un prix affiche que personne ne paiera.
         *
         * Le metier n'appartient a aucune zone : la reponse est celle du MARCHE, pas d'un
         * lecteur qui n'est pas connecte.
         */
        $devise = strtoupper((string) config('fx.base_currency', 'EUR'));
        $symboleDevise = app(Money::class)->symbol($devise);

        return view('pages.service-trade', compact(
            'trade',
            'city',
            'cityLabel',
            'zones',
            'seoTitle',
            'seoDescription',
            'devise',
            'symboleDevise',
        ));
    }
}
