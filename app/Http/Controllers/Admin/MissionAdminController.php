<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mission;

class MissionAdminController extends Controller
{
    public function show(Mission $mission)
    {
        $mission->load([
            'rendezVous.client',
            'organizationAccount',
            'organizationSite',
            'serviceCatalog',
            'serviceZone',
            'leadEmployee',
            'assignments.user',
            'trackingSessions.points',
            'activeTrackingSession',
            'verificationCodes',
            'checklists.items',
            'media',
            'incidents.reportedBy',
            'qualityReviews.reviewer',
            'report',
            'events.actor',
        ]);

        return view('admin.missions.show', compact('mission'));
    }
}