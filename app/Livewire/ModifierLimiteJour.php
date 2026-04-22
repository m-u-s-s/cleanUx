<?php

namespace App\Livewire;

use App\Models\LimiteJournaliere;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ModifierLimiteJour extends Component
{
    public $date;
    public $user_id;
    public $limite;
    public $fromAdmin = false;
    public $record;

    public function mount($date, $user_id, $fromAdmin = false)
    {
        $this->date = $date;
        $this->user_id = $user_id;
        $this->fromAdmin = $fromAdmin;

        $this->record = LimiteJournaliere::where('user_id', $user_id)
            ->where('date', $date)
            ->first();

        $this->limite = $this->record?->limite ?? null;
    }

    public function updatedLimite()
    {
        $targetUser = User::findOrFail($this->user_id);
        $isAdmin = Auth::user()?->role === 'admin';

        // Cas 1 : modification depuis l’admin
        if ($this->fromAdmin) {
            Gate::authorize('manage', User::class);
        }
        // Cas 2 : modification normale par l’utilisateur lui-même
        else {
            if (Auth::id() !== $targetUser->id) {
                abort(403);
            }
        }

        $record = LimiteJournaliere::firstOrCreate(
            [
                'user_id' => $this->user_id,
                'date' => $this->date,
            ],
            [
                'limite' => 0,
                'verrou_admin' => false,
            ]
        );

        // Si verrouillé par admin, un utilisateur normal ne peut plus modifier
        if ($record->verrou_admin && !$isAdmin && !$this->fromAdmin) {
            $this->dispatch(
                'toast',
                __('Modification refusée : cette limite est verrouillée par un administrateur.'),
                'error'
            );
            return;
        }

        $record->limite = $this->limite;

        // Si l’admin modifie depuis l’interface admin, on verrouille
        if ($this->fromAdmin && $isAdmin) {
            $record->verrou_admin = true;
        }

        $record->save();
        $this->record = $record;

        $this->dispatch('toast', __('Limite mise à jour.'), 'success');
    }

    public function render()
    {
        return view('livewire.modifier-limite-jour');
    }
}