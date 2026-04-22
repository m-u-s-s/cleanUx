<?php

namespace App\Livewire\Employe;

use App\Models\Mission;
use App\Services\Missions\MissionQualityService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MissionIncidentBoard extends Component
{
    public Mission $mission;

    public string $title = '';
    public string $description = '';
    public string $incidentType = 'general';
    public string $severity = 'medium';
    public bool $clientVisible = true;

    public ?string $successMessage = null;

    public function mount(Mission $mission): void
    {
        abort_unless($mission->exists, 404);

        $isAssigned = $mission->lead_employee_id === Auth::id()
            || $mission->assignments()->where('user_id', Auth::id())->exists();

        abort_unless($isAssigned, 403);

        $this->mission = $mission;
    }

    public function submit(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'incidentType' => ['required', 'string', 'max:50'],
            'severity' => ['required', 'in:low,medium,high,critical'],
        ]);

        app(MissionQualityService::class)->reportIncident(
            $this->mission->fresh(),
            Auth::user(),
            [
                'title' => $this->title,
                'description' => $this->description,
                'incident_type' => $this->incidentType,
                'severity' => $this->severity,
                'client_visible' => $this->clientVisible,
            ]
        );

        $this->reset(['title', 'description']);
        $this->successMessage = 'Incident enregistré.';
        $this->mission = $this->mission->fresh(['incidents', 'report']);
    }

    public function render()
    {
        return view('livewire.employe.mission-incident-board');
    }
}