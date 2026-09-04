<?php

namespace App\Support\Livewire\Concerns\Admin;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait BootsAdminDashboardFilters
{
    public function mount()
    {
        $admin = $this->currentAdmin();

        if ($admin?->isZoneScopedAdmin()) {
            $this->filtreZone = (string) $admin->managed_service_zone_id;
            $this->zoneScopeLocked = true;
        }

        $this->refreshFilterCollections();

        $this->mettreAJourStats();
        $this->chargerRdvs();
        $this->visibleDashboardSections = session('admin_dashboard.visible_sections', $this->visibleDashboardSections);
        $this->compactMode = session('admin_dashboard.compact_mode', $this->compactMode);
        $this->realtimeEnabled = session('admin_dashboard.realtime_enabled', $this->realtimeEnabled);
        $this->executiveMode = session('admin_dashboard.executive_mode', $this->executiveMode);
    }

    public function updatedFiltreEmploye()
    {
        $this->clearAdminCache();
        $this->mettreAJourStats();
        $this->chargerRdvs();
    }

    public function updatedFiltreZone()
    {
        if ($this->zoneScopeLocked) {
            return;
        }

        $this->clearAdminCache();
        $this->refreshFilterCollections();
        $this->mettreAJourStats();
        $this->chargerRdvs();
    }

    public function mettreAJourStats()
    {
        $baseQuery = $this->scopedRendezVousQuery();

        $this->statistiquesData = Cache::remember($this->cacheKey('statistiquesData'), now()->addMinutes(10), function () use ($baseQuery) {
            return [
                'confirme' => (clone $baseQuery)->where('status', 'confirme')->count(),
                'attente' => (clone $baseQuery)->where('status', 'en_attente')->count(),
                'refuse' => (clone $baseQuery)->where('status', 'refuse')->count(),
                'en_route' => (clone $baseQuery)->where('status', 'en_route')->count(),
                'sur_place' => (clone $baseQuery)->where('status', 'sur_place')->count(),
                'termine' => (clone $baseQuery)->where('status', 'termine')->count(),
            ];
        });

        $this->statsMensuelles = Cache::remember($this->cacheKey('statsMensuelles'), now()->addMinutes(10), function () use ($baseQuery) {
            // L'ANNEE MANQUAIT : `whereMonth` seul empilait tous les janviers de tous les ans dans
            // la meme barre. Une requete groupee remplace au passage les douze comptages.
            $expressionDuMois = DB::connection()->getDriverName() === 'sqlite'
                ? "CAST(strftime('%m', date) AS INTEGER)"
                : 'MONTH(date)';

            $comptes = (clone $baseQuery)
                ->whereYear('date', now()->year)
                ->selectRaw($expressionDuMois.' as mois, count(*) as total')
                ->groupBy('mois')
                ->pluck('total', 'mois')
                ->mapWithKeys(fn ($total, $mois) => [(int) $mois => (int) $total]);

            return collect(range(1, 12))->map(fn ($mois) => $comptes[$mois] ?? 0)->toArray();
        });

        // LE CA SUIT LE MEME PERIMETRE QUE LES RDV. Deux courbes cote a cote sur deux perimetres
        // differents mentiraient : celle-ci lit le filtre de zone comme sa voisine.
        $this->caMensuel = Cache::remember($this->cacheKey('caMensuel'), now()->addMinutes(10), function () use ($baseQuery) {
            $expressionDuMois = DB::connection()->getDriverName() === 'sqlite'
                ? "CAST(strftime('%m', date) AS INTEGER)"
                : 'MONTH(date)';

            $montants = (clone $baseQuery)
                ->whereYear('date', now()->year)
                ->selectRaw($expressionDuMois.' as mois, SUM(devis_estime) as total')
                ->groupBy('mois')
                ->pluck('total', 'mois')
                ->mapWithKeys(fn ($total, $mois) => [(int) $mois => round((float) $total, 2)]);

            return collect(range(1, 12))->map(fn ($mois) => $montants[$mois] ?? 0.0)->toArray();
        });

        $this->dispatch('updateChartData', data: $this->statistiquesData);
        $this->dispatch('updateMonthlyChart', data: $this->statsMensuelles, ca: $this->caMensuel);
    }

    public function chargerRdvs()
    {
        $query = $this->scopedRendezVousQuery()
            ->with(['client', 'employe', 'serviceZone']);

        $this->rdvs = $query->get()->map(function ($rdv) {
            return [
                'title' => ($rdv->client->name ?? 'Client').' → '.($rdv->employe->name ?? 'Employé'),
                'start' => $rdv->date.'T'.substr((string) $rdv->heure, 0, 5),
                'zone' => $rdv->serviceZone?->name,
                'color' => match ($rdv->status) {
                    'confirme' => '#22c55e',
                    'refuse' => '#ef4444',
                    'en_attente' => '#facc15',
                    'en_route' => '#2563eb',
                    'sur_place' => '#4f46e5',
                    'termine' => '#047857',
                    default => '#60a5fa',
                },
            ];
        })->toArray();
    }
}
