<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Models\Booking;
use App\Models\Mission;

/**
 * La lecture business : volumes et valeur.
 *
 * Les MONTANTS sont servis bruts et formatés côté mobile selon leur type déclaré : envoyer une
 * chaîne déjà mise en forme retirerait à l’application le choix de sa présentation.
 */
class BusinessReport implements AdminReport
{
    public function key(): string
    {
        return 'business';
    }

    public function sections(): array
    {
        return [
            [
                'title' => 'Volumes',
                'tiles' => [
                    ReportTile::make(
                        'bookings_total',
                        'Réservations',
                        fn () => Booking::count(),
                    ),
                    ReportTile::make(
                        'missions_completed',
                        'Missions terminées',
                        fn () => Mission::where('status', 'completed')->count(),
                    ),
                ],
            ],
            [
                'title' => 'Valeur',
                'tiles' => [
                    ReportTile::make(
                        'revenue_missions',
                        'Valeur des missions terminées',
                        fn () => (float) Mission::where('status', 'completed')->sum('client_price'),
                        format: 'money',
                    ),
                    ReportTile::make(
                        'commission',
                        'Commission cumulée',
                        fn () => (float) Mission::where('status', 'completed')->sum('platform_commission'),
                        format: 'money',
                    ),
                ],
            ],
        ];
    }
}
