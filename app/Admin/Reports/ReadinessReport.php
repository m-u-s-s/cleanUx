<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Models\ProviderProfile;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\Trade;

/** La préparation de la plateforme au lancement. Chaque tuile répond à « est-ce prêt ? */
class ReadinessReport implements AdminReport
{
    public function key(): string
    {
        return 'readiness';
    }

    public function sections(): array
    {
        return [
            [
                'title' => 'Couverture',
                'tiles' => [
                    ReportTile::make(
                        'trades_active',
                        'Métiers actifs',
                        fn () => Trade::where('is_active', true)->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_SUCCESS : ReportTile::TONE_DANGER,
                    ),
                    ReportTile::make(
                        'services_active',
                        'Prestations actives',
                        fn () => ServiceCatalog::where('is_active', true)->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_SUCCESS : ReportTile::TONE_DANGER,
                    ),
                    ReportTile::make(
                        'zones_bookable',
                        'Zones réservables',
                        fn () => ServiceZone::where('is_bookable', true)->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_SUCCESS : ReportTile::TONE_DANGER,
                    ),
                    ReportTile::make(
                        'providers_verified',
                        'Prestataires vérifiés',
                        fn () => ProviderProfile::where('verification_status', 'verified')->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_SUCCESS : ReportTile::TONE_WARNING,
                    ),
                ],
            ],
        ];
    }
}
