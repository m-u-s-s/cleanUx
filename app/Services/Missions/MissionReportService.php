<?php

namespace App\Services\Missions;

use App\Models\Mission;
use Barryvdh\DomPDF\Facade\Pdf;

class MissionReportService
{
    public function generate(Mission $mission): string
    {
        $mission->load([
            'rendezVous.client',
            'leadEmployee',
            'media',
            'checklists.items',
        ]);

        $pdf = Pdf::loadView('pdf.mission-report', [
            'mission' => $mission,
        ]);

        $path = 'reports/mission-'.$mission->id.'.pdf';

        \Storage::disk('public')->put($path, $pdf->output());

        return $path;
    }
}