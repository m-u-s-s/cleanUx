<?php

namespace App\Livewire\Admin;

use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class UtilisateursAdmin extends Component
{
    use WithPagination;

    public $search = '';
    public $role = '';

    protected $queryString = ['search', 'role', 'page'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingRole()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = User::query()
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('email', 'like', '%' . $this->search . '%')
                        ->orWhere('tva_number', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->role, fn ($q) => $q->where('role', $this->role));

        return view('livewire.admin.utilisateurs-admin', [
            'users' => $query->orderBy('name')->paginate(10),
        ])->layout('layouts.app');
    }
}