<?php

namespace App\Support\Livewire\Concerns\Admin;

use Illuminate\Support\Facades\Cache;

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
                return collect(range(1, 12))->map(function ($mois) use ($baseQuery) {
                    return (clone $baseQuery)->whereMonth('date', $mois)->count();
                })->toArray();
            });

            $this->dispatch('updateChartData', data: $this->statistiquesData);
            $this->dispatch('updateMonthlyChart', data: $this->statsMensuelles);
        }

        public function chargerRdvs()
        {
            $query = $this->scopedRendezVousQuery()
                ->with(['client', 'employe', 'serviceZone']);

            $this->rdvs = $query->get()->map(function ($rdv) {
                return [
                    'title' => ($rdv->client->name ?? 'Client') . ' → ' . ($rdv->employe->name ?? 'Employé'),
                    'start' => $rdv->date . 'T' . substr((string) $rdv->heure, 0, 5),
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
