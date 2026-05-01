<?php

namespace App\Livewire;

use App\Models\Feedback;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\Domain\BookingStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redirect;
use Livewire\Component;
use Livewire\WithPagination;

class AdminFeedbacks extends Component
{
    use WithPagination;

    public ?int $scopeId = null;

    public string $employe_id = '';
    public string $client_id = '';
    public string $status = '';
    public string $search = '';

    public $perPage = 8;

    public array $reponse = [];

    protected $queryString = [
        'employe_id' => ['except' => ''],
        'client_id' => ['except' => ''],
        'status' => ['except' => ''],
        'search' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function mount(?int $scopeId = null): void
    {
        $this->scopeId = $scopeId;
    }

    public function updatedEmployeId(): void
    {
        $this->resetPage();
    }

    public function updatedClientId(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    protected function effectiveScopeId(): ?int
    {
        $user = auth()->user();

        if ($this->scopeId) {
            return $this->scopeId;
        }

        if ($user?->isZoneScopedAdmin() && filled($user->managed_service_zone_id)) {
            return (int) $user->managed_service_zone_id;
        }

        return null;
    }

    protected function baseFeedbacksQuery(): Builder
    {
        $scopeId = $this->effectiveScopeId();

        return Feedback::query()
            ->with([
                'client',
                'rendezVous.client',
                'rendezVous.employe',
                'rendezVous.serviceCatalog',
                'rendezVous.serviceZone',
            ])
            ->when(
                $scopeId,
                fn (Builder $query) => $query->whereHas(
                    'rendezVous',
                    fn (Builder $rdvQuery) => $rdvQuery->where('service_zone_id', $scopeId)
                )
            )
            ->when(
                $this->employe_id !== '',
                fn (Builder $query) => $query->whereHas(
                    'rendezVous',
                    fn (Builder $rdvQuery) => $rdvQuery->where('employe_id', $this->employe_id)
                )
            )
            ->when(
                $this->client_id !== '',
                fn (Builder $query) => $query->where('client_id', $this->client_id)
            )
            ->when(
                $this->status !== '',
                fn (Builder $query) => $query->whereHas(
                    'rendezVous',
                    fn (Builder $rdvQuery) => $rdvQuery->where('status', $this->status)
                )
            )
            ->when(
                trim($this->search) !== '',
                fn (Builder $query) => $this->applySearch($query, trim($this->search))
            );
    }

    protected function applySearch(Builder $query, string $search): Builder
    {
        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';

        return $query->where(function (Builder $searchQuery) use ($like) {
            $searchQuery
                ->where('commentaire', 'like', $like)
                ->orWhere('reponse_admin', 'like', $like)
                ->orWhereHas('client', function (Builder $clientQuery) use ($like) {
                    $clientQuery
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like);
                })
                ->orWhereHas('rendezVous', function (Builder $rdvQuery) use ($like) {
                    $rdvQuery
                        ->where('ville', 'like', $like)
                        ->orWhere('adresse', 'like', $like)
                        ->orWhere('service_type', 'like', $like)
                        ->orWhereHas('employe', function (Builder $employeQuery) use ($like) {
                            $employeQuery
                                ->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        })
                        ->orWhereHas('serviceCatalog', function (Builder $serviceQuery) use ($like) {
                            $serviceQuery
                                ->where('name', 'like', $like)
                                ->orWhere('code', 'like', $like)
                                ->orWhere('slug', 'like', $like);
                        });
                });
        });
    }

    protected function employesQuery(): Builder
    {
        $scopeId = $this->effectiveScopeId();

        return User::query()
            ->where('role', User::ROLE_EMPLOYE)
            ->when($scopeId, function (Builder $query) use ($scopeId) {
                $query->where(function (Builder $subQuery) use ($scopeId) {
                    $subQuery
                        ->where('primary_service_zone_id', $scopeId)
                        ->orWhereHas('zoneAssignments', function (Builder $assignmentQuery) use ($scopeId) {
                            $assignmentQuery
                                ->where('service_zone_id', $scopeId)
                                ->where('is_active', true);
                        });
                });
            })
            ->orderBy('name');
    }

    protected function clientsQuery(): Builder
    {
        $scopeId = $this->effectiveScopeId();

        return User::clientFacing()
            ->when($scopeId, function (Builder $query) use ($scopeId) {
                $query->where(function (Builder $subQuery) use ($scopeId) {
                    $subQuery
                        ->where('primary_service_zone_id', $scopeId)
                        ->orWhereHas('rendezVousClient', function (Builder $rdvQuery) use ($scopeId) {
                            $rdvQuery->where('service_zone_id', $scopeId);
                        });
                });
            })
            ->orderBy('name');
    }

    protected function qualityStats(Builder $query): array
    {
        $rows = (clone $query)->get(['id', 'note', 'reponse_admin']);

        $average = $rows->avg('note');
        $answered = $rows->filter(fn (Feedback $feedback) => filled($feedback->reponse_admin))->count();
        $lowScores = $rows->filter(fn (Feedback $feedback) => (int) $feedback->note <= 2)->count();

        return [
            'total' => $rows->count(),
            'average_note' => $average ? round((float) $average, 1) : 0,
            'average_note_label' => $average ? round((float) $average, 1) . '/5' : '0/5',
            'answered' => $answered,
            'unanswered' => max(0, $rows->count() - $answered),
            'low_scores' => $lowScores,
            'satisfaction_rate' => $average ? (int) round(((float) $average / 5) * 100) : 0,
        ];
    }

    protected function statusOptions(): array
    {
        return [
            '' => 'Tous',
            BookingStatus::EN_ATTENTE => 'En attente',
            BookingStatus::CONFIRME => 'Confirmé',
            BookingStatus::EN_ROUTE => 'En route',
            BookingStatus::SUR_PLACE => 'Sur place',
            BookingStatus::TERMINE => 'Terminé',
            BookingStatus::REFUSE => 'Refusé',
        ];
    }

    public function resetFilters(): void
    {
        $this->reset([
            'employe_id',
            'client_id',
            'status',
            'search',
        ]);

        $this->resetPage();
    }

    public function filterByStatus(string $val): void
    {
        $this->status = $val;
        $this->resetPage();
    }

    public function exportPdf()
    {
        Gate::authorize('export', Feedback::class);

        return Redirect::away(route('admin.feedbacks.export', [
            'employe_id' => $this->employe_id,
            'client_id' => $this->client_id,
            'status' => $this->status,
        ]));
    }

    public function exportCsv()
    {
        Gate::authorize('export', Feedback::class);

        return Redirect::away(route('admin.feedbacks.export.csv', [
            'employe_id' => $this->employe_id,
            'client_id' => $this->client_id,
            'status' => $this->status,
        ]));
    }

    public function updatedReponse($value, $key): void
    {
        Gate::authorize('respond', Feedback::class);

        $scopeId = $this->effectiveScopeId();
        $value = trim((string) $value);

        if (mb_strlen($value) > 2000) {
            $this->dispatch('toast', 'La réponse est trop longue.', 'error');
            return;
        }

        $feedback = Feedback::query()
            ->with(['client', 'rendezVous.employe'])
            ->when(
                $scopeId,
                fn (Builder $query) => $query->whereHas(
                    'rendezVous',
                    fn (Builder $rdvQuery) => $rdvQuery->where('service_zone_id', $scopeId)
                )
            )
            ->find($key);

        if (! $feedback) {
            $this->dispatch('toast', 'Feedback introuvable.', 'error');
            return;
        }

        $feedback->update([
            'reponse_admin' => $value !== '' ? $value : null,
        ]);

        ActivityLogger::log('feedback_repondu_par_admin', $feedback, [
            'feedback_id' => $feedback->id,
            'note' => $feedback->note,
            'client' => $feedback->client->name ?? null,
            'employe' => $feedback->rendezVous->employe->name ?? null,
        ]);

        $this->dispatch('toast', 'Réponse enregistrée.', 'success');
    }

    protected function syncResponsesForPage(Collection $feedbacks): void
    {
        foreach ($feedbacks as $feedback) {
            if (! array_key_exists($feedback->id, $this->reponse)) {
                $this->reponse[$feedback->id] = $feedback->reponse_admin ?? '';
            }
        }
    }

    protected function activeFiltersLabel(): string
    {
        $count = collect([
            $this->employe_id,
            $this->client_id,
            $this->status,
            trim($this->search),
        ])->filter()->count();

        return $count === 0
            ? 'Aucun filtre actif'
            : $count . ' filtre(s) actif(s)';
    }

    public function render(): View
    {
        $feedbacksQuery = $this->baseFeedbacksQuery();

        $feedbacks = (clone $feedbacksQuery)
            ->orderByDesc('created_at')
            ->paginate((int) $this->perPage);

        $this->syncResponsesForPage($feedbacks->getCollection());

        return view('livewire.admin-feedbacks', [
            'feedbacks' => $feedbacks,
            'employes' => $this->employesQuery()->get(),
            'clients' => $this->clientsQuery()->get(),
            'qualityStats' => $this->qualityStats($feedbacksQuery),
            'statusOptions' => $this->statusOptions(),
            'activeFiltersLabel' => $this->activeFiltersLabel(),
            'scopeLabel' => $this->effectiveScopeId() ? 'Zone filtrée' : 'Global',
        ]);
    }
}
