<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class UtilisateursAdmin extends Component
{
    use EnforcesAdminAccess;
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

    public function render(): View
    {
        $query = User::query()
            ->when($this->search, function ($q) {
                $q->where(function ($sub) {
                    $sub->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%')
                        ->orWhere('tva_number', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->role, fn ($q) => $q->where('role', $this->role));

        return view('livewire.admin.utilisateurs-admin', [
            'users' => $query->orderBy('name')->paginate(10),
        ]);
    }
}
