<?php

namespace App\Livewire\Admin;

use App\Models\Mission;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class MissionQualityAnalytics extends Component
{
    use EnforcesAdminAccess;

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

        /*
            LA JOINTURE MANQUANTE — `bookings` n'était jamais amenée dans la requête.
            Elle partait de `missions` et raccrochait `postal_codes` sur
            `bookings.postal_code_id`, une table absente du FROM : la base répondait
            « no such column ». L'écran entier tombait donc en erreur, ce que
            personne ne voyait puisque aucune route n'y menait.

            Le pays d'une mission se lit via sa réservation : mission → booking →
            code postal → pays.
        */
        $byCountry = DB::table('missions')
            ->leftJoin('bookings', 'bookings.id', '=', 'missions.booking_id')
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
