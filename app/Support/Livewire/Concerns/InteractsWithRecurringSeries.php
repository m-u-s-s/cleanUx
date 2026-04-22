<?php

namespace App\Support\Livewire\Concerns;

use App\Actions\Booking\CancelRecurringSeriesAction;
use App\Actions\Booking\UpdateRecurringSeriesAction;
use App\Models\RendezVous;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;

trait InteractsWithRecurringSeries
{
    public int $rendezVousId;
    public string $scope = 'occurrence';
    public ?string $editDate = null;
    public ?string $editHeure = null;
    public ?int $editEmployeId = null;
    public string $contextLabel = 'série';

    public function mountRecurringSeries(RendezVous $rendezVous): void
    {
        Gate::authorize('view', $rendezVous);

        abort_unless($rendezVous->recurring_series_id, 404);

        $this->rendezVousId = $rendezVous->id;
        $this->editDate = optional($rendezVous->date)->format('Y-m-d');
        $this->editHeure = substr((string) $rendezVous->heure, 0, 5);
        $this->editEmployeId = $rendezVous->employe_id;
    }

    #[Computed]
    public function currentRendezVous(): RendezVous
    {
        return RendezVous::query()
            ->with(['employe', 'serviceZone', 'serviceCatalog', 'client'])
            ->findOrFail($this->rendezVousId);
    }

    #[Computed]
    public function seriesOccurrences()
    {
        return RendezVous::query()
            ->with(['employe'])
            ->where('recurring_series_id', $this->currentRendezVous->recurring_series_id)
            ->orderBy('series_position')
            ->orderBy('date')
            ->orderBy('heure')
            ->get();
    }

    #[Computed]
    public function assignableEmployees()
    {
        $zoneId = $this->currentRendezVous->service_zone_id;

        return User::query()
            ->where('role', 'employe')
            ->where('is_active', true)
            ->when($zoneId, function ($query) use ($zoneId) {
                $query->where(function ($employeeQuery) use ($zoneId) {
                    $employeeQuery
                        ->where('primary_service_zone_id', $zoneId)
                        ->orWhereHas('zoneAssignments', function ($assignmentQuery) use ($zoneId) {
                            $assignmentQuery
                                ->where('service_zone_id', $zoneId)
                                ->where('is_active', true);
                        });
                });
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public function saveChanges(): void
    {
        Gate::authorize('update', $this->currentRendezVous);

        $this->validate([
            'scope' => ['required', 'in:occurrence,future,series'],
            'editDate' => ['required', 'date'],
            'editHeure' => ['required', 'date_format:H:i'],
            'editEmployeId' => ['nullable', 'exists:users,id'],
        ]);

        app(UpdateRecurringSeriesAction::class)->execute($this->currentRendezVous, [
            'date' => $this->editDate,
            'heure' => $this->editHeure,
            'employe_id' => $this->editEmployeId,
        ], $this->scope);

        session()->flash('success', 'La série récurrente a été mise à jour.');
        $this->dispatch('toast', 'La série récurrente a été mise à jour.', 'success');
    }

    public function pauseSeries(string $scope = 'future'): void
    {
        Gate::authorize('update', $this->currentRendezVous);
        app(CancelRecurringSeriesAction::class)->pause($this->currentRendezVous, $scope);
        session()->flash('success', 'La série a été mise en pause.');
        $this->dispatch('toast', 'La série a été mise en pause.', 'success');
    }

    public function resumeSeries(string $scope = 'future'): void
    {
        Gate::authorize('update', $this->currentRendezVous);
        app(CancelRecurringSeriesAction::class)->resume($this->currentRendezVous, $scope);
        session()->flash('success', 'La série a été réactivée.');
        $this->dispatch('toast', 'La série a été réactivée.', 'success');
    }

    public function cancelSeries(string $scope = 'future'): void
    {
        Gate::authorize('delete', $this->currentRendezVous);
        app(CancelRecurringSeriesAction::class)->cancel($this->currentRendezVous, $scope);
        session()->flash('success', 'La série a été annulée.');
        $this->dispatch('toast', 'La série a été annulée.', 'success');
    }
}
