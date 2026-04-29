<?php

namespace App\Livewire\Admin;

use App\Models\CustomerCredit;
use App\Models\RendezVous;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerCreditsManager extends Component
{
    use WithPagination;

    public ?int $client_id = null;
    public ?int $rendez_vous_id = null;
    public string $type = 'commercial_gesture';
    public float $amount = 0;
    public string $reason = '';
    public string $notes = '';
    public ?string $expires_at = null;

    public string $search = '';

    protected $paginationTheme = 'tailwind';

    public function createCredit(): void
    {
        $this->validate([
            'client_id' => ['required', 'exists:users,id'],
            'rendez_vous_id' => ['nullable', 'exists:rendez_vous,id'],
            'type' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:1', 'max:5000'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $credit = CustomerCredit::create([
            'client_id' => $this->client_id,
            'rendez_vous_id' => $this->rendez_vous_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'remaining_amount' => $this->amount,
            'status' => 'active',
            'reason' => $this->reason,
            'notes' => $this->notes,
            'expires_at' => $this->expires_at,
        ]);

        ActivityLogger::log('customer_credit_created', $credit, [
            'client_id' => $this->client_id,
            'rendez_vous_id' => $this->rendez_vous_id,
            'amount' => $this->amount,
            'reason' => $this->reason,
        ]);

        $this->reset([
            'client_id',
            'rendez_vous_id',
            'amount',
            'reason',
            'notes',
            'expires_at',
        ]);

        $this->type = 'commercial_gesture';

        $this->dispatch('toast', 'Crédit client ajouté avec succès.', 'success');
    }

    public function cancelCredit(int $creditId): void
    {
        $credit = CustomerCredit::findOrFail($creditId);

        if ($credit->status !== 'active') {
            $this->dispatch('toast', 'Ce crédit ne peut pas être annulé.', 'error');
            return;
        }

        $credit->update([
            'status' => 'cancelled',
            'remaining_amount' => 0,
        ]);

        ActivityLogger::log('customer_credit_cancelled', $credit, [
            'credit_id' => $credit->id,
            'client_id' => $credit->client_id,
        ]);

        $this->dispatch('toast', 'Crédit annulé.', 'success');
    }

    public function render(): View
    {
        return view('livewire.admin.customer-credits-manager', [
            'clients' => User::query()
                ->clientFacing()
                ->orderBy('name')
                ->get(),

            'rendezVous' => RendezVous::query()
                ->when($this->client_id, fn ($query) => $query->where('client_id', $this->client_id))
                ->latest()
                ->limit(50)
                ->get(),

            'credits' => CustomerCredit::query()
                ->with(['client', 'rendezVous'])
                ->when($this->search, function ($query) {
                    $query->whereHas('client', function ($clientQuery) {
                        $clientQuery->where('name', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%');
                    });
                })
                ->latest()
                ->paginate(10),
        ]);
    }
}