<?php

namespace App\Livewire\Employe;

use App\Models\Disponibilite;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

class DisponibilitesEmploye extends Component
{
    public string $weekStart = '';
    public string $date = '';
    public string $heure_debut = '08:00';
    public string $heure_fin = '12:00';
    public ?int $editingId = null;

    public function mount(): void
    {
        $this->weekStart = now()->startOfWeek()->toDateString();
        $this->date = now()->toDateString();
    }

    public function previousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->toDateString();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->date = now()->toDateString();
        $this->heure_debut = '08:00';
        $this->heure_fin = '12:00';
    }

    public function save(): void
    {
        $this->validate([
            'date' => ['required', 'date'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
        ]);

        $data = [
            'user_id' => Auth::id(),
            'date' => $this->date,
            'heure_debut' => $this->heure_debut,
            'heure_fin' => $this->heure_fin,
        ];

        if ($this->editingId) {
            $slot = Disponibilite::where('user_id', Auth::id())->findOrFail($this->editingId);
            $slot->update($data);
            ActivityLogger::log('disponibilite_modifiee', $slot, $data);
            $message = 'Disponibilité mise à jour.';
        } else {
            $exists = Disponibilite::where('user_id', Auth::id())
                ->where('date', $this->date)
                ->where('heure_debut', '<', $this->heure_fin)
                ->where('heure_fin', '>', $this->heure_debut)
                ->exists();

            if ($exists) {
                $this->addError('heure_debut', 'Ce créneau chevauche déjà une disponibilité.');
                return;
            }

            $slot = Disponibilite::create($data);
            ActivityLogger::log('disponibilite_creee', $slot, $data);
            $message = 'Disponibilité ajoutée.';
        }

        $this->resetForm();
        $this->dispatch('toast', $message, 'success');
    }

    public function edit(int $id): void
    {
        $slot = Disponibilite::where('user_id', Auth::id())->findOrFail($id);

        $this->editingId = $slot->id;
        $this->date = optional($slot->date)->toDateString() ?? $slot->date;
        $this->heure_debut = substr((string) $slot->heure_debut, 0, 5);
        $this->heure_fin = substr((string) $slot->heure_fin, 0, 5);
    }

    public function delete(int $id): void
    {
        $slot = Disponibilite::where('user_id', Auth::id())->findOrFail($id);
        ActivityLogger::log('disponibilite_supprimee', $slot, [
            'date' => optional($slot->date)->toDateString() ?? $slot->date,
            'heure_debut' => $slot->heure_debut,
            'heure_fin' => $slot->heure_fin,
        ]);
        $slot->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        $this->dispatch('toast', 'Disponibilité supprimée.', 'success');
    }

    public function blockDay(string $date): void
    {
        $slots = Disponibilite::where('user_id', Auth::id())->whereDate('date', $date)->get();

        foreach ($slots as $slot) {
            ActivityLogger::log('disponibilite_supprimee', $slot, [
                'date' => optional($slot->date)->toDateString() ?? $slot->date,
                'blocked_day' => true,
            ]);
            $slot->delete();
        }

        $this->dispatch('toast', 'Journée bloquée : tous les créneaux ont été retirés.', 'success');
    }

    public function getWeekDaysProperty()
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek();

        return collect(range(0, 6))->map(fn ($offset) => $start->copy()->addDays($offset));
    }

    public function getSlotsByDayProperty()
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek();
        $end = $start->copy()->endOfWeek();

        return Disponibilite::where('user_id', Auth::id())
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('date')
            ->orderBy('heure_debut')
            ->get()
            ->groupBy(fn ($slot) => optional($slot->date)->toDateString() ?? $slot->date);
    }

    public function render(): View
    {
        return view('livewire.employe.disponibilites-employe', [
            'weekDays' => $this->weekDays,
            'slotsByDay' => $this->slotsByDay,
        ]);
    }
}
