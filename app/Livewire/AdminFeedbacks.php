<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Feedback;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redirect;

class AdminFeedbacks extends Component
{
    use WithPagination;

    public ?int $scopeId = null;
    public $employe_id = '';
    public $client_id = '';
    public $perPage = 5;
    public $status = '';
    public $reponse = [];

    protected $queryString = ['employe_id', 'client_id', 'status', 'page'];

    public function mount(?int $scopeId = null): void
    {
        $this->scopeId = $scopeId;
    }

    public function updatedEmployeId()
    {
        $this->resetPage();
    }

    public function updatedClientId()
    {
        $this->resetPage();
    }

    public function updatedStatus()
    {
        $this->resetPage();
    }

    public function exportPdf()
    {
        Gate::authorize('export', Feedback::class);

        $url = route('admin.feedbacks.export', [
            'employe_id' => $this->employe_id,
            'client_id' => $this->client_id,
            'status' => $this->status,
        ]);

        return Redirect::away($url);
    }

    public function exportCsv()
    {
        Gate::authorize('export', Feedback::class);

        $url = route('admin.feedbacks.export.csv', [
            'employe_id' => $this->employe_id,
            'client_id' => $this->client_id,
            'status' => $this->status,
        ]);

        return Redirect::away($url);
    }

    public function updatedReponse($value, $key)
    {
        Gate::authorize('respond', Feedback::class);

        $feedback = Feedback::with(['client', 'rendezVous.employe'])->find($key);

        if (! $feedback) {
            $this->dispatch('toast', 'Feedback introuvable.', 'error');
            return;
        }

        $feedback->update([
            'reponse_admin' => $value,
        ]);

        ActivityLogger::log('feedback_repondu_par_admin', $feedback, [
            'feedback_id' => $feedback->id,
            'note' => $feedback->note,
            'client' => $feedback->client->name ?? null,
            'employe' => $feedback->rendezVous->employe->name ?? null,
        ]);

        $this->dispatch('toast', 'Réponse enregistrée.', 'success');
    }

    public function filterByStatus($val)
    {
        $this->status = $val;
        $this->resetPage();
    }

    public function render()
    {
        $feedbacksQuery = Feedback::with(['client', 'rendezVous.employe'])
            ->when(
                $this->scopeId,
                fn ($q) => $q->whereHas('rendezVous', fn ($r) => $r->where('service_zone_id', $this->scopeId))
            )
            ->when(
                $this->employe_id,
                fn ($q) => $q->whereHas('rendezVous', fn ($r) => $r->where('employe_id', $this->employe_id))
            )
            ->when(
                $this->status,
                fn ($q) => $q->whereHas('rendezVous', fn ($r) => $r->where('status', $this->status))
            )
            ->when(
                $this->client_id,
                fn ($q) => $q->where('client_id', $this->client_id)
            );

        $feedbacks = $feedbacksQuery
            ->orderByDesc('created_at')
            ->paginate($this->perPage);

        $employes = User::where('role', 'employe')
            ->when(
                $this->scopeId,
                fn ($q) => $q->where(function ($sub) {
                    $sub->where('primary_service_zone_id', $this->scopeId)
                        ->orWhereHas('zoneAssignments', fn ($a) => $a->where('service_zone_id', $this->scopeId)->where('is_active', true));
                })
            )
            ->orderBy('name')
            ->get();

        $clients = User::clientFacing()
            ->when(
                $this->scopeId,
                fn ($q) => $q->where(function ($sub) {
                    $sub->where('primary_service_zone_id', $this->scopeId)
                        ->orWhereHas('rendezVousClient', fn ($r) => $r->where('service_zone_id', $this->scopeId));
                })
            )
            ->orderBy('name')
            ->get();

        return view('livewire.admin-feedbacks', compact('feedbacks', 'employes', 'clients'));
    }
}
