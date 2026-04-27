<?php

namespace App\Livewire\Admin;

use App\Models\RendezVous;
use App\Services\Dispatch\AiDispatchService;
use App\Services\Missions\MissionFromRendezVousSyncService;
use App\Support\ActivityLogger;
use App\Support\Domain\BookingStatus;
use Livewire\Component;
use Livewire\WithPagination;

class AiDispatchCenter extends Component
{
    use WithPagination;

    public string $search = '';
    public string $status = 'en_attente';
    public ?int $previewRdvId = null;
    public array $ranking = [];

    protected $paginationTheme = 'tailwind';

    public function preview(int $rdvId, AiDispatchService $dispatch): void
    {
        $rdv = RendezVous::with(['client', 'serviceZone', 'employe'])
            ->findOrFail($rdvId);

        $this->previewRdvId = $rdv->id;

        $this->ranking = $dispatch->rankEmployees($rdv)
            ->map(fn ($row) => [
                'employee_id' => $row['employee']->id,
                'name' => $row['employee']->name,
                'score' => $row['score'],
                'details' => $row['details'],
            ])
            ->toArray();
    }

    public function closePreview(): void
    {
        $this->previewRdvId = null;
        $this->ranking = [];
    }

    public function assign(int $rdvId, AiDispatchService $dispatch): void
    {
        $rdv = RendezVous::with(['client', 'serviceZone', 'employe', 'mission'])
            ->findOrFail($rdvId);

        $employee = $dispatch->bestEmployeeFor($rdv);

        if (! $employee) {
            $this->dispatch('toast', 'Aucun employé disponible trouvé.', 'error');
            return;
        }

        $oldEmployeeId = $rdv->employe_id;

        $rdv->update([
            'employe_id' => $employee->id,
            'status' => BookingStatus::CONFIRME,
        ]);

        app(MissionFromRendezVousSyncService::class)
            ->syncFromRendezVous($rdv->fresh());

        ActivityLogger::log('ai_dispatch_assigned', $rdv, [
            'old_employee_id' => $oldEmployeeId,
            'new_employee_id' => $employee->id,
            'new_employee_name' => $employee->name,
        ]);

        $this->dispatch('toast', 'IA Dispatch : rendez-vous assigné à '.$employee->name.'.', 'success');
    }

    public function render()
    {
        return view('livewire.admin.ai-dispatch-center', [
            'rendezVous' => RendezVous::query()
                ->with(['client', 'employe', 'serviceZone'])
                ->when($this->status, fn ($q) => $q->where('status', $this->status))
                ->when($this->search, fn ($q) => $q->searchStructured($this->search))
                ->orderBy('date')
                ->orderBy('heure')
                ->paginate(10),
        ]);
    }
}