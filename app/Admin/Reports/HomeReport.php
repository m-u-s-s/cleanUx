<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\KycVerification;
use App\Models\Mission;
use App\Support\Domain\MissionStatus;

/**
 * L’accueil de l’administration : ce qui demande une attention aujourd’hui.
 *
 * Les files d’attente sont en ton d’alerte dès qu’elles ne sont pas vides — une file non vide
 * est une charge de travail, et l’annoncer est tout l’intérêt de cet écran.
 */
class HomeReport implements AdminReport
{
    public function key(): string
    {
        return 'home';
    }

    public function sections(): array
    {
        return [
            [
                'title' => 'À traiter',
                'tiles' => [
                    ReportTile::make(
                        'bookings_pending',
                        'Réservations en attente',
                        fn () => Booking::pending()->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_WARNING : ReportTile::TONE_SUCCESS,
                    ),
                    ReportTile::make(
                        'claims_open',
                        'Litiges ouverts',
                        fn () => ComplaintCase::whereNotIn('status', [ComplaintCase::STATUS_RESOLVED, ComplaintCase::STATUS_CLOSED])->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_DANGER : ReportTile::TONE_SUCCESS,
                    ),
                    ReportTile::make(
                        'kyc_pending',
                        'KYC à traiter',
                        fn () => KycVerification::pending()->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_WARNING : ReportTile::TONE_SUCCESS,
                    ),
                ],
            ],
            [
                'title' => 'Terrain',
                'tiles' => [
                    ReportTile::make(
                        'missions_active',
                        'Missions en cours',
                        fn () => Mission::whereIn('status', MissionStatus::trackable())->count(),
                    ),
                ],
            ],
        ];
    }
}
