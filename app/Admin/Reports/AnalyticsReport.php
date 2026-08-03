<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;

/**
 * La synthèse analytique.
 *
 * Les COURBES restent sur le web. Une série temporelle lisible demande de la largeur ; la
 * réduire à un téléphone produit un graphique qu’on ne peut pas lire, donc qu’on n’utilise pas.
 * Ici, les compteurs qui se lisent d’un coup d’œil.
 */
class AnalyticsReport implements AdminReport
{
    public function key(): string
    {
        return 'analytics';
    }

    public function sections(): array
    {
        return [
            [
                'title' => 'Mesures',
                'tiles' => [
                    ReportTile::make(
                        'events',
                        'Événements enregistrés',
                        fn () => AnalyticsEvent::count(),
                    ),
                    ReportTile::make(
                        'events_today',
                        'Événements du jour',
                        fn () => AnalyticsEvent::whereDate('created_at', today())->count(),
                    ),
                    ReportTile::make(
                        'sessions',
                        'Sessions',
                        fn () => AnalyticsSession::count(),
                    ),
                ],
            ],
        ];
    }
}
