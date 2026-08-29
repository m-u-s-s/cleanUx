<?php

namespace App\Livewire\PeerRental;

use App\Models\PeerRental;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * MES LOCATIONS — CELLES QUE JE PRENDS, CELLES QUE JE LOUE.
 *
 * Un membre est souvent les deux : `role` bascule la lecture au lieu d'ouvrir deux ecrans
 * qui se ressembleraient.
 */
#[Layout('layouts.app')]
class PeerMyRentals extends Component
{
    use WithPagination;

    /** `renter` : ce que je loue. `owner` : ce que je prete. */
    #[Url(as: 'role', except: 'renter')]
    public string $role = 'renter';

    #[Url(as: 'statut', except: '')]
    public string $statut = '';

    public function updatedRole(): void
    {
        $this->resetPage();
    }

    public function updatedStatut(): void
    {
        $this->resetPage();
    }

    /** @return LengthAwarePaginator<int, PeerRental> */
    #[Computed]
    public function locations(): LengthAwarePaginator
    {
        return PeerRental::query()
            ->with(['vehicle.media', 'owner:id,name', 'renter:id,name'])
            ->where($this->role === 'owner' ? 'owner_id' : 'renter_id', auth()->id())
            ->when($this->statut !== '', fn (Builder $q) => $q->where('status', $this->statut))
            ->orderByDesc('starts_at')
            ->paginate(12);
    }

    /** @return array<string, int> */
    #[Computed]
    public function compteurs(): array
    {
        $base = fn (): Builder => PeerRental::query()
            ->where($this->role === 'owner' ? 'owner_id' : 'renter_id', auth()->id());

        return [
            'a_traiter' => (int) $base()->whereIn('status', [
                PeerRental::STATUT_EN_ATTENTE, PeerRental::STATUT_CONFIRMEE,
            ])->count(),
            'en_cours' => (int) $base()->where('status', PeerRental::STATUT_EN_COURS)->count(),
            'terminees' => (int) $base()->where('status', PeerRental::STATUT_TERMINEE)->count(),
            'litiges' => (int) $base()->where('status', PeerRental::STATUT_LITIGE)->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.peer-rental.peer-my-rentals');
    }
}
