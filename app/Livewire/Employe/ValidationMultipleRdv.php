<?php

namespace App\Livewire\Employe;

use Livewire\Component;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ValidationMultipleRdv extends Component
{
    public $selection = [];

    public function toggleSelection($id)
    {
        if (in_array($id, $this->selection)) {
            $this->selection = array_diff($this->selection, [$id]);
        } else {
            $this->selection[] = $id;
        }
    }

    public function validerSelection()
    {
        $rdvs = Booking::whereIn('id', $this->selection)->get();

        foreach ($rdvs as $rdv) {
            Gate::authorize('update', $rdv);
        }

        Booking::whereIn('id', $this->selection)
            ->where('employe_id', Auth::id())
            ->update(['status' => 'confirme']);

        $this->selection = [];
        session()->flash('success', '✅ Rendez-vous validés avec succès.');
    }

    public function refuserSelection()
    {
        $rdvs = Booking::whereIn('id', $this->selection)->get();

        foreach ($rdvs as $rdv) {
            Gate::authorize('update', $rdv);
        }

        Booking::whereIn('id', $this->selection)
            ->where('employe_id', Auth::id())
            ->update(['status' => 'refuse']);

        $this->selection = [];
        session()->flash('success', '❌ Rendez-vous refusés.');
    }

    public function render()
    {
        $rdvs = Booking::where('employe_id', Auth::id())
            ->where('status', 'en_attente')
            ->orderBy('date')
            ->get();

        return view('livewire.employe.validation-multiple-rdv', compact('rdvs'));
    }
}