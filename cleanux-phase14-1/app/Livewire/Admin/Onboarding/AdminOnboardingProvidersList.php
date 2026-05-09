<?php

namespace App\Livewire\Admin\Onboarding;

use App\Models\ProviderOnboardingDocument;
use App\Models\ProviderProfile;
use App\Services\Onboarding\ProviderOnboardingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Phase 14.1 — Liste des prestataires en cours d'onboarding (vue admin).
 *
 * Route : /admin/onboarding-providers
 *
 * Permet à l'admin de :
 *   - Voir tous les prestataires par statut (en cours, prêts à valider, validés)
 *   - Cliquer pour voir le détail d'un prestataire (documents, étapes)
 *   - Approuver l'onboarding final pour ceux prêts (tous docs OK + Stripe actif)
 */
class AdminOnboardingProvidersList extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $search = '';
    public string $filterStatus = 'in_progress'; // in_progress | ready | verified | all

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterStatus()
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterStatus = 'in_progress';
        $this->resetPage();
    }

    public function getProvidersProperty()
    {
        $query = ProviderProfile::query()
            ->with(['user:id,name,email,phone,stripe_connect_status'])
            ->orderByDesc('updated_at');

        // Filtre par statut
        $query->when($this->filterStatus, function ($q, $status) {
            return match ($status) {
                'in_progress' => $q->where('verification_status', '!=', 'verified')
                                   ->whereNull('onboarding_completed_at'),
                'ready' => $q->where('verification_status', '!=', 'verified')
                             ->where('onboarding_step', '>=', 5),
                'verified' => $q->where('verification_status', 'verified'),
                default => $q,
            };
        });

        // Recherche
        $query->when($this->search !== '', function ($q) {
            $term = '%' . trim($this->search) . '%';
            $q->whereHas('user', function ($u) use ($term) {
                $u->where('name', 'like', $term)
                  ->orWhere('email', 'like', $term);
            });
        });

        return $query->paginate(15);
    }

    public function getCountsProperty(): array
    {
        return [
            'in_progress' => ProviderProfile::where('verification_status', '!=', 'verified')
                ->whereNull('onboarding_completed_at')->count(),
            'ready' => ProviderProfile::where('verification_status', '!=', 'verified')
                ->where('onboarding_step', '>=', 5)->count(),
            'verified' => ProviderProfile::where('verification_status', 'verified')->count(),
        ];
    }

    public function approveOnboarding(int $userId): void
    {
        $user = \App\Models\User::find($userId);
        if (! $user) {
            session()->flash('error', 'Utilisateur introuvable.');
            return;
        }

        try {
            app(ProviderOnboardingService::class)->approveOnboarding($user, Auth::user());
            session()->flash('success', "Onboarding de {$user->name} approuvé. Il peut maintenant recevoir des missions.");
        } catch (\DomainException $e) {
            session()->flash('error', "Impossible d'approuver : {$e->getMessage()}");
        } catch (\Throwable $e) {
            report($e);
            session()->flash('error', "Erreur lors de l'approbation.");
        }
    }

    /**
     * Compte les documents par statut pour un user (helper UI).
     */
    public function documentsCountFor(int $userId): array
    {
        return [
            'pending'  => ProviderOnboardingDocument::forUser($userId)->where('status', 'pending_review')->count(),
            'approved' => ProviderOnboardingDocument::forUser($userId)->approved()->count(),
            'rejected' => ProviderOnboardingDocument::forUser($userId)->where('status', 'rejected')->count(),
        ];
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        return view('livewire.admin.onboarding.admin-onboarding-providers-list', [
            'providers' => $this->providers,
            'counts'    => $this->counts,
        ]);
    }
}
