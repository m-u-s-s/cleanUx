<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Models\MarketingCampaign;
use App\Models\RiskEvaluation;

/**
 * L’automatisation : ce qui tourne sans intervention.
 *
 * Le nombre de campagnes actives et de règles de risque déclenchées dit si les automatismes
 * travaillent. Les DÉCLENCHER à la main depuis ici irait contre leur raison d’être.
 */
class AutomationReport implements AdminReport
{
    public function key(): string
    {
        return 'automation';
    }

    public function sections(): array
    {
        return [
            [
                'title' => 'En marche',
                'tiles' => [
                    ReportTile::make(
                        'campaigns_running',
                        'Campagnes en cours',
                        fn () => MarketingCampaign::where('status', 'running')->count(),
                    ),
                    ReportTile::make(
                        'risk_reviews',
                        'Évaluations de risque à revoir',
                        fn () => RiskEvaluation::where('decision', 'review')->count(),
                        tone: fn ($v) => $v > 0 ? ReportTile::TONE_WARNING : ReportTile::TONE_SUCCESS,
                    ),
                ],
            ],
        ];
    }
}
