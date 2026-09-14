<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Models\Booking;
use App\Models\Mission;

/** La lecture business : volumes et valeur. */
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
                        // LA COMMISSION VIT SUR LA RÉSERVATION, EN CENTIMES. `missions.platform_commission`
                        // existe, n'est écrite nulle part, et cette tuile affichait donc 0,00 € à vie.
                        fn () => (float) Booking::query()
                            ->whereHas('missions', fn ($m) => $m->where('status', 'completed'))
                            ->sum('platform_fee_cents') / 100,
                        format: 'money',
                    ),
                ],
            ],
        ];
    }
}
