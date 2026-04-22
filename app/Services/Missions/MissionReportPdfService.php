<?php

namespace App\Services\Missions;

use App\Models\Mission;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MissionReportPdfService
{
    public function download(Mission $mission)
    {
        $mission->load([
            'rendezVous.client',
            'leadEmployee',
            'checklists.items',
            'media',
            'incidents',
            'qualityReviews',
            'report',
            'events.actor',
        ]);

        $pdf = Pdf::loadView('pdf.mission-report', [
            'mission' => $mission,
            'report' => $mission->report,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('mission-report-'.$mission->id.'.pdf');
    }
}