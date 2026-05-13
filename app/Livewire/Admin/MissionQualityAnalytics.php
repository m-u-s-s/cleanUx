<?php

namespace App\Livewire\Admin;

use App\Models\Mission;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class MissionQualityAnalytics extends Component
{
    public function render()
    {
        $byZone = Mission::query()
            ->leftJoin('service_zones', 'service_zones.id', '=', 'missions.service_zone_id')
            ->whereNotNull('missions.quality_score')
            ->selectRaw('service_zones.name as label, AVG(missions.quality_score) as avg_score, COUNT(missions.id) as missions_count')
            ->groupBy('service_zones.name')
            ->orderByDesc('avg_score')
            ->limit(15)
            ->get();

        $byService = Mission::query()
            ->leftJoin('service_catalogs', 'service_catalogs.id', '=', 'missions.service_catalog_id')
            ->whereNotNull('missions.quality_score')
            ->selectRaw('service_catalogs.name as label, AVG(missions.quality_score) as avg_score, COUNT(missions.id) as missions_count')
            ->groupBy('service_catalogs.name')
            ->orderByDesc('avg_score')
            ->limit(15)
            ->get();

        $byCountry = DB::table('missions')
            ->leftJoin('rendez_vous', 'bookings.id', '=', 'missions.rendez_vous_id')
            ->leftJoin('postal_codes', 'postal_codes.id', '=', 'bookings.postal_code_id')
            ->leftJoin('countries', 'countries.id', '=', 'postal_codes.country_id')
            ->whereNotNull('missions.quality_score')
            ->selectRaw('countries.name as label, AVG(missions.quality_score) as avg_score, COUNT(missions.id) as missions_count')
            ->groupBy('countries.name')
            ->orderByDesc('avg_score')
            ->limit(15)
            ->get();

        return view('livewire.admin.mission-quality-analytics', [
            'byZone' => $byZone,
            'byService' => $byService,
            'byCountry' => $byCountry,
        ]);
    }
}