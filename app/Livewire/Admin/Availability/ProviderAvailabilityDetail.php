<?php

namespace App\Livewire\Admin\Availability;

use App\Models\AvailabilityException;
use App\Models\AvailabilitySlot;
use App\Models\User;
use App\Services\Availability\AvailabilityEditor;
use App\Services\Availability\DefaultAvailabilityProvisioner;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** LA FICHE DE DISPONIBILITÉ D'UN PRESTATAIRE, CÔTÉ ADMINISTRATION. */
class ProviderAvailabilityDetail extends Component
{
    use EnforcesAdminAccess;

    /** Le prestataire dont on édite la semaine — jamais l'administrateur connecté. */
    #[Locked]
    public int $providerId;

    public int $weekday = 1;

    public string $heure_debut = '08:00';

    public string $heure_fin = '17:00';

    #[Locked]
    public ?int $editingId = null;

    public string $weekStart = '';

    public string $exceptionReason = '';

    public function mount(User $user): void
    {
        // ON REFUSE ICI, PAS À L'ÉCRITURE.
        abort_unless($user->isEmploye(), 404);

        $this->providerId = $user->id;
        $this->weekStart = now()->startOfWeek()->toDateString();
    }

    public function provider(): User
    {
        return User::findOrFail($this->providerId);
    }

    // ─── Navigation ──────────────────────────────────────────────────────────────────────────

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
        $this->weekday = 1;
        $this->heure_debut = '08:00';
        $this->heure_fin = '17:00';
        $this->resetErrorBag();
    }

    // ─── Écritures ───────────────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->validate([
            'weekday' => ['required', 'integer', 'between:0,6'],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i', 'after:heure_debut'],
        ]);

        $resultat = app(AvailabilityEditor::class)->saveSlot(
            $this->provider(),
            $this->weekday,
            $this->heure_debut,
            $this->heure_fin,
            $this->editingId,
        );

        if ($resultat === AvailabilityEditor::CHEVAUCHEMENT) {
            $this->addError('heure_debut', __('Ce créneau en recouvre un autre le même jour.'));

            return;
        }

        $this->resetForm();
        $this->dispatch('toast', __('Semaine du prestataire mise à jour.'), 'success');
    }

    public function edit(int $id): void
    {
        $slot = AvailabilitySlot::where('provider_user_id', $this->providerId)->findOrFail($id);

        $this->editingId = $slot->id;
        $this->weekday = (int) $slot->weekday;
        $this->heure_debut = substr((string) $slot->start_time, 0, 5);
        $this->heure_fin = substr((string) $slot->end_time, 0, 5);
    }

    public function delete(int $id): void
    {
        app(AvailabilityEditor::class)->deleteSlot($this->provider(), $id);

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        $this->dispatch('toast', __('Créneau retiré.'), 'success');
    }

    public function closeDay(string $date): void
    {
        app(AvailabilityEditor::class)->closeDay($this->provider(), $date, $this->exceptionReason);

        $this->exceptionReason = '';
        $this->dispatch('toast', __('Journée fermée pour ce prestataire.'), 'success');
    }

    public function reopenDay(int $id): void
    {
        app(AvailabilityEditor::class)->reopenDay($this->provider(), $id);

        $this->dispatch('toast', __('Journée rouverte.'), 'success');
    }

    /** Poser la semaine par défaut sur un compte qui n'a rien. */
    public function applyDefaultWeek(): void
    {
        $crees = app(DefaultAvailabilityProvisioner::class)->provision($this->provider());

        $this->dispatch(
            'toast',
            $crees > 0
                ? __('Semaine par défaut appliquée (:count créneaux).', ['count' => $crees])
                : __('Ce prestataire a déjà une semaine à lui : rien n’a été touché.'),
            $crees > 0 ? 'success' : 'info',
        );
    }

    // ─── Lectures ────────────────────────────────────────────────────────────────────────────

    /** @return Collection<int|string, EloquentCollection<int, AvailabilitySlot>> */
    public function slotsByWeekday(): Collection
    {
        return AvailabilitySlot::query()
            ->where('provider_user_id', $this->providerId)
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (AvailabilitySlot $slot) => (int) $slot->weekday);
    }

    /** @return Collection<int, Carbon> */
    public function weekDays(): Collection
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek();

        return collect(range(0, 6))->map(fn ($offset) => $start->copy()->addDays($offset));
    }

    /** @return Collection<string, AvailabilityException> */
    public function closedDays(): Collection
    {
        $start = Carbon::parse($this->weekStart)->startOfWeek();

        return AvailabilityException::query()
            ->where('provider_user_id', $this->providerId)
            ->where('exception_type', AvailabilityException::TYPE_CLOSED)
            ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfWeek()->toDateString()])
            ->get()
            ->keyBy(fn (AvailabilityException $e) => $e->date->format('Y-m-d'));
    }

    public function render(): View
    {
        return view('livewire.admin.availability.provider-availability-detail', [
            'provider' => $this->provider(),
            'slotsByWeekday' => $this->slotsByWeekday(),
            'weekDays' => $this->weekDays(),
            'closedDays' => $this->closedDays(),
        ]);
    }
}
