<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;

/** Le tableau de bord d’administration. */
class DashboardReport implements AdminReport
{
    public function key(): string
    {
        return 'dashboard';
    }

    public function sections(): array
    {
        return [
            [
                'title' => 'Activité',
                'tiles' => [
                    ReportTile::make(
                        'users',
                        'Comptes',
                        fn () => User::count(),
                    ),
                    ReportTile::make(
                        'bookings',
                        'Réservations',
                        fn () => Booking::count(),
                    ),
                    ReportTile::make(
                        'bookings_today',
                        'Réservations du jour',
                        fn () => Booking::whereDate('scheduled_date', today())->count(),
                    ),
                    ReportTile::make(
                        'missions',
                        'Missions',
                        fn () => Mission::count(),
                    ),
                ],
            ],
        ];
    }
}
