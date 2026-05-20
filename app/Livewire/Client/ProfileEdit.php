<?php

namespace App\Livewire\Client;

use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProfileEdit extends Component
{
    public string $name = '';
    public string $email = '';
    public string $phone = '';

    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = (string) $user->name;
        $this->email = (string) $user->email;
        $this->phone = (string) ($user->phone ?? '');
    }

    public function updateProfile(UpdateUserProfileInformation $action): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
        ]);

        try {
            $action->update(Auth::user(), [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone ?: null,
            ]);
            $this->dispatch('toast', 'Profil mis à jour.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function updatePassword(UpdateUserPassword $action): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $action->update(Auth::user(), [
                'current_password' => $this->current_password,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ]);
            $this->reset(['current_password', 'password', 'password_confirmation']);
            $this->dispatch('toast', 'Mot de passe mis à jour.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        return view('livewire.client.profile-edit')->layout('layouts.app');
    }
}
