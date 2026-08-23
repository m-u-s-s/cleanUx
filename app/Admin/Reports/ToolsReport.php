<?php

namespace App\Admin\Reports;

use App\Admin\Console\AdminReport;
use App\Admin\Console\ReportTile;
use App\Models\AccountingExport;
use App\Models\GdprDataRequest;

/** Les outils et exports. */
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
