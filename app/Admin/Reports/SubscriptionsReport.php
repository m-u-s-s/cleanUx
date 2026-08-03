<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Models\SubscriptionsV2\SubscriptionCycleV2;
use App\Models\SubscriptionsV2\SubscriptionV2;

/**
 * Les abonnements et leur santé.
 *
 * Un abonnement en défaut de paiement finit annulé automatiquement s’il n’est pas rattrapé :
 * le compteur `past_due` est donc le seul qui appelle une action, et il est annoncé comme tel.
 */
class SubscriptionsReport implements AdminReport
{
    public function key(): string
    {
        return 'subscriptions';
    }

    public function sections(): array
    {
        return [
            [
                'title' => 'Parc',
                'tiles' => [
                    ReportTile::make(
                        'active',
                        'Abonnements actifs',
                        fn () => SubscriptionV2::where('status', 'active')->count(),
                    ),
                    ReportTile::make(
                        'past_due',
                        'En défaut de paiement',
                        fn () => SubscriptionV2::where('status', 'past_due')->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_DANGER : ReportTile::TONE_SUCCESS,
                    ),
                    ReportTile::make(
                        'cycles',
                        'Cycles de facturation',
                        fn () => SubscriptionCycleV2::count(),
                    ),
                ],
            ],
        ];
    }
}
