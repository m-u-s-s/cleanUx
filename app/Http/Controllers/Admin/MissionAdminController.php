<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\User;



class MissionAdminController extends Controller
{
    public function show(Mission $mission)
    {
        abort_unless(auth()->user()?->isAdmin(), 403);

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
