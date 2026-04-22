<?php

namespace App\Livewire\Employe;

use App\Models\Mission;
use App\Services\Missions\MissionLifecycleService;
use App\Services\Missions\MissionTrackingService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MissionActions extends Component
{
    public Mission $mission;

    public string $startCode = '';
    public string $endCode = '';

    public ?string $generatedStartCode = null;
    public ?string $generatedEndCode = null;

    public ?string $successMessage = null;
    public ?string $errorMessage = null;

    public function mount(Mission $mission): void
    {
        $this->mission = $mission->load(['assignments', 'verificationCodes', 'rendezVous']);
    }

    public function setEnRoute(): void
    {
        $this->resetMessages();

        try {
            $this->mission = $this->service()->setEnRoute(
                $this->mission->fresh(),
                Auth::user()
            );

            $this->successMessage = 'Mission passée en route.';
            $this->dispatch('mission-en-route-start-tracking', missionId: $this->mission->id);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function setArrived(): void
    {
        $this->resetMessages();

        try {
            $this->mission = $this->service()->setArrived(
                $this->mission->fresh(),
                Auth::user()
            );

            $this->generatedStartCode = session('mission_start_code_' . $this->mission->id);
            $this->successMessage = 'Arrivée confirmée. Code de début généré.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function startMission(): void
    {
        $this->resetMessages();

        $this->validate([
            'startCode' => ['required', 'digits:6'],
        ]);

        try {
            $this->mission = $this->service()->validateStartCode(
                $this->mission->fresh(),
                Auth::user(),
                $this->startCode
            );

            $this->startCode = '';
            $this->generatedStartCode = null;
            $this->successMessage = 'Mission démarrée avec succès.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function prepareEndCode(): void
    {
        $this->resetMessages();

        try {
            $generated = $this->service()->generateEndCode($this->mission->fresh());
            $this->generatedEndCode = $generated['code'];

            session()->put('mission_end_code_' . $this->mission->id, $generated['code']);

            $this->mission = $this->mission->fresh(['assignments', 'verificationCodes', 'rendezVous']);

            $this->successMessage = 'Code de fin généré.';
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    public function finishMission(): void
    {
        $this->resetMessages();

        $this->validate([
            'endCode' => ['required', 'digits:6'],
        ]);

        try {
            $this->mission = $this->service()->validateEndCode(
                $this->mission->fresh(),
                Auth::user(),
                $this->endCode
            );

            $this->endCode = '';
            $this->generatedEndCode = null;
            $this->successMessage = 'Mission terminée avec succès.';
            session()->forget('mission_end_code_' . $this->mission->id);
            session()->forget('mission_start_code_' . $this->mission->id);
        } catch (\Throwable $e) {
            $this->errorMessage = $e->getMessage();
        }
    }

    protected function service(): MissionLifecycleService
    {
        return app(MissionLifecycleService::class);
    }

    protected function resetMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function render()
    {
        return view('livewire.employe.mission-actions');
    }
}
