<?php

namespace App\Livewire\Client;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FavoriteEmployesManager extends Component
{
    public string $search = '';

    public function isPremiumClient(): bool
    {
        return Auth::check() && Auth::user()->isPremium();
    }

    public function getFavoriteIdsProperty(): array
    {
        if (!$this->isPremiumClient()) {
            return [];
        }

        return Auth::user()
            ->favoriteEmployes()
            ->pluck('users.id')
            ->toArray();
    }

    public function getEmployesProperty()
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
        if (!$this->isPremiumClient()) {
            $this->dispatch('toast', 'Cette fonctionnalité est réservée aux clients Premium.', 'error');
            return;
        }

        $client = Auth::user();

        if (!$client->favoriteEmployes()->where('users.id', $employeId)->exists()) {
            $client->favoriteEmployes()->attach($employeId, [
                'is_favorite' => true,
            ]);
        }

        $this->dispatch('toast', 'Employé ajouté à vos favoris.', 'success');
    }

    public function removeFavorite(int $employeId): void
    {
        if (!$this->isPremiumClient()) {
            $this->dispatch('toast', 'Cette fonctionnalité est réservée aux clients Premium.', 'error');
            return;
        }

        $client = Auth::user();
        $client->favoriteEmployes()->detach($employeId);

        $this->dispatch('toast', 'Employé retiré de vos favoris.', 'success');
    }

    public function render()
    {
        return view('livewire.client.favorite-employes-manager', [
            'employes' => $this->employes,
            'favoriteIds' => $this->favoriteIds,
            'isPremium' => $this->isPremiumClient(),
        ])->layout('layouts.app');
    }
}