<?php

namespace App\Livewire;

use App\Support\Livewire\Concerns\ComputesAdminDashboardData;
use App\Support\Livewire\Concerns\HandlesAdminDashboardPlanning;
use Livewire\Component;
use Illuminate\Contracts\View\View;
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

    public function refreshDashboard()
    {
        $this->clearAdminCache();
        $this->mettreAJourStats();
        $this->chargerRdvs();

        $this->dispatch('toast', type: 'success', message: 'Dashboard mis à jour');
    }

    public function goToPlanning()
    {
        return redirect()->route('admin.planning');
    }

    public function goToMissions()
    {
        return redirect()->route('admin.missions');
    }

    public function goToFeedbacks()
    {
        return redirect()->route('admin.feedbacks');
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
