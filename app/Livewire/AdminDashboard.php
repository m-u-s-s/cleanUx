<?php

namespace App\Livewire;

use App\Support\Livewire\Concerns\ComputesAdminDashboardData;
use App\Support\Livewire\Concerns\HandlesAdminDashboardPlanning;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Layout;

class AdminDashboard extends Component
{
    use ComputesAdminDashboardData;
    use HandlesAdminDashboardPlanning;

    public $filtreEmploye = null;
    public $filtreZone = null;
    public array $statistiquesData = [];
    public array $statsMensuelles = [];
    public array $rdvs = [];
    public $employes = [];
    public $clients = [];
    public $employeSelectionne = null;
    public bool $zoneScopeLocked = false;

    public $selectedMissionId = null;
    public $showMissionModal = false;

    public $showPlanningModal = false;
    public $planningMissionId = null;
    public $planningEmployeId = null;
    public $planningDate = null;
    public $planningHeure = null;

    public array $suggestedEmployees = [];
    public ?string $lastDashboardRefreshAt = null;
    public bool $realtimeEnabled = true;
    public string $filtreStatus = '';
    public string $filtrePeriode = 'all';
    public string $dashboardSearch = '';
    public bool $compactMode = true;
    public array $visibleDashboardSections = [
        'operations' => true,
        'analytics' => true,
        'premium' => false,
        'charts' => true,
        'tools' => true,
        'modules' => false,
    ];
    public bool $executiveMode = false;


    public function refreshDashboard(): void
    {
        $this->realtimeRefresh();

        $this->dispatch('toast', type: 'success', message: 'Dashboard mis à jour');
    }

    public function goToPlanning()
    {
        return redirect()->to($this->safeAdminRoute('admin.planning'));
    }

    public function goToMissions()
    {
        return redirect()->to($this->safeAdminRoute('admin.missions'));
    }

    public function goToFeedbacks()
    {
        return redirect()->to($this->safeAdminRoute('admin.feedbacks'));
    }

    protected function safeAdminRoute(string $routeName, string $fallback = 'admin.dashboard'): string
    {
        return Route::has($routeName)
            ? route($routeName)
            : route($fallback);
    }

    public function realtimeRefresh(): void
    {
        if (! $this->realtimeEnabled) {
            return;
        }

        $this->clearAdminCache();
        $this->mettreAJourStats();
        $this->chargerRdvs();

        $this->lastDashboardRefreshAt = now()->format('H:i:s');
    }

    public function toggleRealtime(): void
    {
        $this->realtimeEnabled = ! $this->realtimeEnabled;

        session(['admin_dashboard.realtime_enabled' => $this->realtimeEnabled]);

        $this->dispatch(
            'toast',
            type: $this->realtimeEnabled ? 'success' : 'info',
            message: $this->realtimeEnabled
                ? 'Temps réel activé'
                : 'Temps réel désactivé'
        );
    }

    public function updatedFiltreStatus(): void
    {
        $this->clearAdminCache();
        $this->mettreAJourStats();
        $this->chargerRdvs();
    }

    public function updatedFiltrePeriode(): void
    {
        $this->clearAdminCache();
        $this->mettreAJourStats();
        $this->chargerRdvs();
    }

    public function resetDashboardFilters(): void
    {
        $this->filtreEmploye = null;

        if (! $this->zoneScopeLocked) {
            $this->filtreZone = null;
        }

        $this->filtreStatus = '';
        $this->filtrePeriode = 'all';
        $this->dashboardSearch = '';

        $this->clearAdminCache();
        $this->refreshFilterCollections();
        $this->mettreAJourStats();
        $this->chargerRdvs();

        $this->dispatch('toast', type: 'success', message: 'Filtres réinitialisés');
    }

    public function updatedDashboardSearch(): void
    {
        $this->clearAdminCache();
        $this->mettreAJourStats();
        $this->chargerRdvs();
    }

    public function toggleCompactMode(): void
    {
        $this->compactMode = ! $this->compactMode;

        session(['admin_dashboard.compact_mode' => $this->compactMode]);

        $this->dispatch(
            'toast',
            type: 'info',
            message: $this->compactMode
                ? 'Mode compact activé'
                : 'Mode détaillé activé'
        );
    }

    public function toggleDashboardSection(string $section): void
    {
        if (! array_key_exists($section, $this->visibleDashboardSections)) {
            return;
        }

        $this->visibleDashboardSections[$section] = ! $this->visibleDashboardSections[$section];

        session(['admin_dashboard.visible_sections' => $this->visibleDashboardSections]);

        $this->dispatch(
            'toast',
            type: 'info',
            message: 'Affichage du dashboard mis à jour'
        );
    }

    public function resetDashboardPreferences(): void
    {
        session()->forget('admin_dashboard.executive_mode');
        $this->executiveMode = false;
        session()->forget([
            'admin_dashboard.visible_sections',
            'admin_dashboard.compact_mode',
            'admin_dashboard.realtime_enabled',
        ]);

        $this->visibleDashboardSections = [
            'operations' => true,
            'analytics' => true,
            'premium' => true,
            'charts' => true,
            'tools' => true,
            'modules' => false,
        ];

        $this->compactMode = false;
        $this->realtimeEnabled = true;

        $this->dispatch('toast', type: 'success', message: 'Préférences réinitialisées');
    }

    public function toggleExecutiveMode(): void
    {
        $this->executiveMode = ! $this->executiveMode;

        session(['admin_dashboard.executive_mode' => $this->executiveMode]);

        $this->dispatch(
            'toast',
            type: 'info',
            message: $this->executiveMode
                ? 'Mode exécutif activé'
                : 'Mode exécutif désactivé'
        );
    }

    public function getExecutiveSummaryProperty(): array
    {
        $alerts = [];

        if (($this->adminKpis['urgentes_vieilles'] ?? 0) > 0) {
            $alerts[] = 'Des urgences sont en attente depuis trop longtemps.';
        }

        if (($this->adminKpis['employes_surcharges'] ?? 0) > 0) {
            $alerts[] = 'Certains employés sont surchargés aujourd’hui.';
        }

        if (($this->feedbackRate ?? 0) < 40) {
            $alerts[] = 'Le taux de feedback client est faible.';
        }

        if (($this->adminKpis['en_attente'] ?? 0) > 0) {
            $alerts[] = 'Des rendez-vous sont encore en attente de traitement.';
        }

        return [
            'status' => count($alerts) === 0 ? 'stable' : 'attention',
            'title' => count($alerts) === 0
                ? 'Situation stable'
                : 'Attention requise',
            'message' => count($alerts) === 0
                ? 'Aucune anomalie majeure détectée sur la plateforme.'
                : implode(' ', $alerts),
        ];
    }

    public function getExecutiveActionsProperty(): array
    {
        $actions = [];

        if (($this->adminKpis['en_attente'] ?? 0) > 0) {
            $actions[] = [
                'title' => 'Traiter les rendez-vous en attente',
                'message' => 'Des demandes attendent une validation ou une attribution.',
                'route' => $this->safeAdminRoute('admin.planning'),
                'label' => 'Ouvrir planning',
                'icon' => '⏳',
                'tone' => 'amber',
            ];
        }

        if (($this->adminKpis['urgentes_vieilles'] ?? 0) > 0) {
            $actions[] = [
                'title' => 'Prioriser les urgences anciennes',
                'message' => 'Certaines urgences sont bloquées depuis plus de 4 heures.',
                'route' => route('admin.missions'),
                'label' => 'Voir missions',
                'icon' => '🚨',
                'tone' => 'red',
            ];
        }

        if (($this->adminKpis['employes_surcharges'] ?? 0) > 0) {
            $actions[] = [
                'title' => 'Rééquilibrer la charge terrain',
                'message' => 'Un ou plusieurs employés dépassent une charge élevée.',
                'route' => route('admin.planning'),
                'label' => 'Réorganiser',
                'icon' => '👥',
                'tone' => 'blue',
            ];
        }

        if (($this->feedbackRate ?? 0) < 40) {
            $actions[] = [
                'title' => 'Relancer les feedbacks clients',
                'message' => 'Le taux de feedback est faible. Une relance peut améliorer le suivi qualité.',
                'route' => $this->safeAdminRoute('admin.feedbacks'),
                'label' => 'Voir feedbacks',
                'icon' => '💬',
                'tone' => 'emerald',
            ];
        }

        return array_slice($actions, 0, 4);
    }

    public function render(): View
    {
        return view('livewire.admin-dashboard', [
            'employes' => $this->employes,
            'clients' => $this->clients,
            'stats' => $this->statistiquesData,
            'rdvs' => $this->rdvs,
            'urgences' => $this->urgences,
            'interventionsDuJour' => $this->interventionsDuJour,
            'chargeEmployes' => $this->chargeEmployes,
            'missionsTerminees' => $this->missionsTerminees,
            'qualiteMissions' => $this->qualiteMissions,
            'qualiteStats' => $this->qualiteStats,
            'selectedMission' => $this->selectedMission,
            'recentActivityLogs' => $this->recentActivityLogs,
            'topServices' => $this->topServices,
            'topVilles' => $this->topVilles,
            'dureeStats' => $this->dureeStats,
            'performanceEmployes' => $this->performanceEmployes,
            'feedbackRate' => $this->feedbackRate,
            'recommendations' => $this->recommendations,
            'adminKpis' => $this->adminKpis,
            'urgencesVieillissantes' => $this->urgencesVieillissantes,
            'servicesSousEstimes' => $this->servicesSousEstimes,
            'suggestedEmployees' => $this->suggestedEmployees,
            'premiumClientsCount' => $this->premiumClientsCount,
            'standardClientsCount' => $this->standardClientsCount,
            'activeSubscriptionsCount' => $this->activeSubscriptionsCount,
            'premiumClients' => $this->premiumClients,
            'premiumRendezVous' => $this->premiumRendezVous,
            'rendezVousSansEmploye' => $this->rendezVousSansEmploye,
            'premiumClientsWithoutFavorites' => $this->premiumClientsWithoutFavorites,
            'availableZones' => $this->availableZones,
            'selectedZone' => $this->selectedZone,
            'adminScopeLabel' => $this->adminScopeLabel,
            'zoneOverview' => $this->zoneOverview,
        ]);
    }
}
