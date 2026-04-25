<?php

namespace App\Http\Controllers;

use App\Models\Mission;
use App\Models\User;
use App\Services\Missions\MissionQualityService;
use App\Services\Missions\MissionReportPdfService;
use Illuminate\Support\Facades\Auth;

class MissionReportController extends Controller
{
    public function download(
        Mission $mission,
        MissionQualityService $qualityService,
        MissionReportPdfService $pdfService
    ) {
        abort_unless($this->canDownloadMissionReport($mission), 403);

        $qualityService->refreshMissionQuality($mission->fresh());
        $qualityService->generateOrRefreshReport($mission->fresh(), Auth::user());

        return $pdfService->download($mission->fresh());
    }

    protected function canDownloadMissionReport(Mission $mission): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isEmploye()) {
            return $mission->lead_employee_id === $user->id
                || $mission->assignments()
                    ->where('user_id', $user->id)
                    ->exists();
        }

        if ($user->isClient() || $user->isEntreprise()) {
            return $mission->rendezVous?->client_id === $user->id
                || (
                    $mission->organization_account_id
                    && $user->organization_account_id
                    && $mission->organization_account_id === $user->organization_account_id
                );
        }

        return false;
    }
}