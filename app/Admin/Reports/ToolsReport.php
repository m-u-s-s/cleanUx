<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Models\AccountingExport;
use App\Models\GdprDataRequest;

/**
 * Les outils et exports.
 *
 * Les EXPORTS ne se déclenchent pas depuis un téléphone : ils produisent des fichiers qu’on
 * ouvre sur un poste, et les lancer sans pouvoir les récupérer ne rend service à personne.
 * Cette page dit ce que le système contient ; les exports restent sur le web.
 */
class ToolsReport implements AdminReport
{
    public function key(): string
    {
        return 'tools';
    }

    public function sections(): array
    {
        return [
            [
                'title' => 'Exports disponibles',
                'tiles' => [
                    ReportTile::make(
                        'accounting_exports',
                        'Exports comptables',
                        fn () => AccountingExport::count(),
                    ),
                    ReportTile::make(
                        'gdpr_exports',
                        'Exports RGPD',
                        fn () => GdprDataRequest::where('type', 'export')->count(),
                    ),
                ],
            ],
        ];
    }
}
