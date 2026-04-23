<?php

namespace App\Livewire\Client;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FavoriteEmployesManager extends Component
{
    public string $search = '';

    protected function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public function isPremiumClient(): bool
    {
        $user = $this->currentUser();

        return $user?->isPremium() ?? false;
    }

    public function getFavoriteIdsProperty(): array
    {
        $client = $this->currentUser();

        if (! $client || ! $client->isPremium()) {
            return [];
        }

        return $client
            ->favoriteEmployes()
            ->pluck('users.id')
            ->toArray();
    }

    public function getEmployesProperty(): Collection
    {
        return User::query()
            ->where('role', 'employe')
            ->when($this->search !== '', function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->orderBy('name')
            ->get();
    }

    public function addFavorite(int $employeId): void
    {
        $client = $this->currentUser();

        if (! $client || ! $client->isPremium()) {
            $this->dispatch('toast', 'Cette fonctionnalité est réservée aux clients Premium.', 'error');
            return;
        }

        if (! $client->favoriteEmployes()->where('users.id', $employeId)->exists()) {
            $client->favoriteEmployes()->attach($employeId, [
                'is_favorite' => true,
            ]);
        }

        $this->dispatch('toast', 'Employé ajouté à vos favoris.', 'success');
    }

    public function removeFavorite(int $employeId): void
    {
        $client = $this->currentUser();

        if (! $client || ! $client->isPremium()) {
            $this->dispatch('toast', 'Cette fonctionnalité est réservée aux clients Premium.', 'error');
            return;
        }

        $client->favoriteEmployes()->detach($employeId);

        $this->dispatch('toast', 'Employé retiré de vos favoris.', 'success');
    }

    public function render(): View
    {
        return view('livewire.client.favorite-employes-manager', [
            'employes' => $this->employes,
            'favoriteIds' => $this->favoriteIds,
            'isPremium' => $this->isPremiumClient(),
        ]);
    }
}