<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Services\Missions\MissionQualityService;
use App\Services\Missions\MissionReportPdfService;
use Illuminate\Support\Facades\Auth;

class MissionReportController extends Controller
{
    public function download(Mission $mission, MissionQualityService $qualityService, MissionReportPdfService $pdfService)
    {
        abort_unless(Auth::check(), 403);

        $qualityService->refreshMissionQuality($mission->fresh());
        $qualityService->generateOrRefreshReport($mission->fresh(), Auth::user());

        return $pdfService->download($mission->fresh());
    }
}