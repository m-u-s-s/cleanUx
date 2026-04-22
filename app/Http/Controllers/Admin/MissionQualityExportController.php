<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\MissionIncident;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MissionQualityExportController extends Controller
{
    public function incidentsCsv(): StreamedResponse
    {
        $filename = 'mission-incidents-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'incident_id',
                'mission_id',
                'booking_reference',
                'title',
                'incident_type',
                'severity',
                'status',
                'reported_by',
                'reported_at',
            ]);

            MissionIncident::query()
                ->with(['mission.rendezVous', 'reportedBy'])
                ->orderByDesc('id')
                ->chunk(200, function ($rows) use ($handle) {
                    foreach ($rows as $incident) {
                        fputcsv($handle, [
                            $incident->id,
                            $incident->mission_id,
                            $incident->mission?->rendezVous?->booking_reference,
                            $incident->title,
                            $incident->incident_type,
                            $incident->severity,
                            $incident->status,
                            $incident->reportedBy?->name,
                            optional($incident->reported_at)->format('Y-m-d H:i:s'),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function missionsCsv(): StreamedResponse
    {
        $filename = 'mission-quality-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'mission_id',
                'booking_reference',
                'status',
                'quality_score',
                'quality_status',
                'client_final_status',
                'employee',
                'service',
                'zone',
                'actual_start_at',
                'actual_end_at',
            ]);

            Mission::query()
                ->with(['rendezVous', 'leadEmployee', 'serviceCatalog', 'serviceZone'])
                ->orderByDesc('id')
                ->chunk(200, function ($rows) use ($handle) {
                    foreach ($rows as $mission) {
                        fputcsv($handle, [
                            $mission->id,
                            $mission->rendezVous?->booking_reference,
                            $mission->status,
                            $mission->quality_score,
                            $mission->quality_status,
                            $mission->client_final_status,
                            $mission->leadEmployee?->name,
                            $mission->serviceCatalog?->name,
                            $mission->serviceZone?->name,
                            optional($mission->actual_start_at)->format('Y-m-d H:i:s'),
                            optional($mission->actual_end_at)->format('Y-m-d H:i:s'),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}