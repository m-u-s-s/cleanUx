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


    public function approveOnboarding(int $providerId): void
    {
        $profile = ProviderProfile::query()
            ->where('user_id', $providerId)
            ->first();

        if (! $profile) {
            $profile = ProviderProfile::query()->findOrFail($providerId);
        }


        $documentsQuery = \App\Models\ProviderOnboardingDocument::query()
            ->where('user_id', $providerId);

        $totalDocuments = $documentsQuery->count();

        $hasBlockingDocuments = \App\Models\ProviderOnboardingDocument::query()
            ->where('user_id', $providerId)
            ->whereNotIn('status', ['approved', 'valid', 'validated'])
            ->exists();

        if ($totalDocuments === 0 || $hasBlockingDocuments) {
            $this->addError('onboarding', 'Impossible d’approuver ce prestataire : les documents requis ne sont pas encore validés.');
            return;
        }

        // Garde ici ton contrôle existant sur les documents si tu en as un.

        $profile->forceFill([
            'verification_status' => 'verified',
            'status' => 'active',
            'onboarding_completed_at' => now(),
        ])->save();

        session()->flash('success', 'Prestataire validé avec succès.');
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

    public function mount(): void
    {
        $this->loadCounts();
    }

    public function refreshCounts(): void
    {
        $this->loadCounts();
    }

    protected function loadCounts(): void
    {
        $this->counts = $this->calculateCounts();
    }





    protected function refreshOnboardingCounts(): void
    {
        $this->refreshCounts();
    }

    protected function calculateCounts(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('provider_profiles')) {
            return [
                'in_progress' => 0,
                'ready' => 0,
                'verified' => 0,
                'pending' => 0,
                'rejected' => 0,
                'total' => 0,
            ];
        }

        $profiles = \Illuminate\Support\Facades\DB::table('provider_profiles')->get();

        $inProgress = 0;
        $ready = 0;
        $verified = 0;
        $pending = 0;
        $rejected = 0;

        foreach ($profiles as $profile) {
            $status = (string) ($profile->status ?? '');
            $verification = (string) ($profile->verification_status ?? '');
            $step = (int) ($profile->onboarding_step ?? 0);
            $completedAt = $profile->onboarding_completed_at ?? null;

            if ($verification === 'rejected') {
                $rejected++;
                continue;
            }

            if ($verification === 'verified' || $status === 'active' || ! empty($completedAt)) {
                $verified++;
                continue;
            }

            if ($verification === 'pending') {
                $pending++;
            }

            if ($step >= 5) {
                $ready++;
                continue;
            }

            if ($step > 0 && $step < 5) {
                $inProgress++;
            }
        }

        return [
            'in_progress' => $inProgress,
            'ready' => $ready,
            'verified' => $verified,
            'pending' => $pending,
            'rejected' => $rejected,
            'total' => $profiles->count(),
        ];
    }







    public function render(): View
    {
        $this->loadCounts();
        $this->counts = $this->recalculateProviderOnboardingCountsStable();

        $this->refreshProviderOnboardingCounts();
        return view('livewire.admin.onboarding.admin-onboarding-providers-list', [
            'providers' => $this->providers,
            'counts'    => $this->counts,
        ]);
    }

    public function getCountsProperty(): array
    {
        $hasOnboardingStatus = \Illuminate\Support\Facades\Schema::hasColumn('provider_profiles', 'onboarding_status');
        $hasVerificationStatus = \Illuminate\Support\Facades\Schema::hasColumn('provider_profiles', 'verification_status');

        if ($hasOnboardingStatus) {
            return [
                'in_progress' => \App\Models\ProviderProfile::query()
                    ->where('onboarding_status', 'in_progress')
                    ->count(),

                'ready' => \App\Models\ProviderProfile::query()
                    ->where('onboarding_status', 'ready')
                    ->count(),

                'verified' => \App\Models\ProviderProfile::query()
                    ->where(function ($query) use ($hasVerificationStatus) {
                        $query->where('onboarding_status', 'verified');

                        if ($hasVerificationStatus) {
                            $query->orWhere('verification_status', 'verified');
                        }
                    })
                    ->count(),
            ];
        }

        dd([
            'profiles' => \App\Models\ProviderProfile::query()
                ->get()
                ->map(fn($p) => [
                    'id' => $p->id,
                    'user_id' => $p->user_id,
                    'status' => $p->status ?? null,
                    'verification_status' => $p->verification_status ?? null,
                    'onboarding_status' => $p->onboarding_status ?? null,
                    'onboarding_completed_at' => $p->onboarding_completed_at ?? null,
                ])
                ->toArray(),
            'counts' => $counts,
        ]);

        return [
            'in_progress' => \App\Models\ProviderProfile::query()
                ->whereIn('verification_status', ['in_progress', 'pending', 'unverified'])
                ->count(),

            'ready' => \App\Models\ProviderProfile::query()
                ->where('verification_status', 'ready')
                ->count(),

            'verified' => \App\Models\ProviderProfile::query()
                ->where('verification_status', 'verified')
                ->count(),
        ];
    }


    private function refreshProviderOnboardingCounts(): void
    {
        $this->counts = $this->providerOnboardingCounts();
    }

    private function providerOnboardingCounts(): array
    {
        $counts = [
            'in_progress' => 0,
            'ready' => 0,
            'verified' => 0,
        ];

        \App\Models\ProviderProfile::query()
            ->get()
            ->filter(fn($profile) => $this->providerHasOnboardingSignal($profile))
            ->each(function ($profile) use (&$counts) {
                $bucket = $this->providerOnboardingBucket($profile);

                if (isset($counts[$bucket])) {
                    $counts[$bucket]++;
                }
            });

        return $counts;
    }

    private function providerOnboardingBucket(\App\Models\ProviderProfile $profile): string
    {
        $verificationStatus = strtolower((string) ($profile->verification_status ?? ''));
        $onboardingStatus = strtolower((string) ($profile->onboarding_status ?? ''));

        if (
            in_array($verificationStatus, ['verified', 'verifie', 'vérifié'], true)
            || in_array($onboardingStatus, ['verified', 'verifie', 'vérifié'], true)
        ) {
            return 'verified';
        }

        if (
            in_array($verificationStatus, ['ready', 'ready_for_review', 'pret', 'prêt'], true)
            || in_array($onboardingStatus, ['ready', 'ready_for_review', 'pret', 'prêt'], true)
            || $this->providerDocumentsAreReady($profile)
        ) {
            return 'ready';
        }

        return 'in_progress';
    }

    private function providerHasOnboardingSignal(\App\Models\ProviderProfile $profile): bool
    {
        $verificationStatus = strtolower((string) ($profile->verification_status ?? ''));
        $onboardingStatus = strtolower((string) ($profile->onboarding_status ?? ''));

        /*
         * On compte les vrais dossiers onboarding.
         * Mais on évite de compter un simple profil demo "pending" sans signal onboarding.
         */
        if (in_array($verificationStatus, [
            'in_progress',
            'started',
            'ready',
            'ready_for_review',
            'verified',
            'verifie',
            'vérifié',
        ], true)) {
            return true;
        }

        if (in_array($onboardingStatus, [
            'in_progress',
            'started',
            'ready',
            'ready_for_review',
            'verified',
            'verifie',
            'vérifié',
        ], true)) {
            return true;
        }

        foreach (
            [
                'onboarding_started_at',
                'onboarding_submitted_at',
                'onboarding_completed_at',
            ] as $column
        ) {
            if (isset($profile->{$column}) && filled($profile->{$column})) {
                return true;
            }
        }

        return $this->providerOnboardingDocuments($profile)->isNotEmpty();
    }




    private function providerDocumentsAreReady(\App\Models\ProviderProfile $profile): bool
    {
        $documents = $this->providerOnboardingDocuments($profile);

        if ($documents->isEmpty()) {
            return false;
        }

        return $documents->every(function ($document) {
            $status = strtolower((string) ($document->status ?? ''));

            return in_array($status, [
                'approved',
                'validated',
                'valide',
                'validé',
                'ready',
                'verified',
            ], true);
        });
    }

    private function providerOnboardingDocuments(\App\Models\ProviderProfile $profile): \Illuminate\Support\Collection
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('provider_onboarding_documents')) {
            return collect();
        }

        $hasProviderUserId = \Illuminate\Support\Facades\Schema::hasColumn('provider_onboarding_documents', 'provider_user_id');
        $hasUserId = \Illuminate\Support\Facades\Schema::hasColumn('provider_onboarding_documents', 'user_id');
        $hasProviderProfileId = \Illuminate\Support\Facades\Schema::hasColumn('provider_onboarding_documents', 'provider_profile_id');

        return \Illuminate\Support\Facades\DB::table('provider_onboarding_documents')
            ->where(function ($query) use ($profile, $hasProviderUserId, $hasUserId, $hasProviderProfileId) {
                if ($hasProviderUserId) {
                    $query->orWhere('provider_user_id', $profile->user_id);
                }

                if ($hasUserId) {
                    $query->orWhere('user_id', $profile->user_id);
                }

                if ($hasProviderProfileId) {
                    $query->orWhere('provider_profile_id', $profile->id);
                }

                if (! $hasProviderUserId && ! $hasUserId && ! $hasProviderProfileId) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->get();
    }


    private function recalculateProviderOnboardingCountsStable(): array
    {
        $counts = [
            'in_progress' => 0,
            'ready' => 0,
            'verified' => 0,
        ];

        $profiles = \App\Models\ProviderProfile::query()->get();

        foreach ($profiles as $profile) {
            $status = $this->normalizeProviderOnboardingStatusStable($profile);

            if (array_key_exists($status, $counts)) {
                $counts[$status]++;
            }
        }

        return $counts;
    }

    private function normalizeProviderOnboardingStatusStable(\App\Models\ProviderProfile $profile): string
    {
        $values = collect([
            $profile->onboarding_status ?? null,
            $profile->verification_status ?? null,
            $profile->status ?? null,
        ])
            ->filter()
            ->map(fn($value) => strtolower(trim((string) $value)))
            ->values()
            ->all();

        /*
         * Ordre important :
         * verified d'abord, puis ready, puis in_progress.
         * Comme ça un profil ready/verified n'est pas aussi compté en in_progress.
         */
        if (
            in_array('verified', $values, true)
            || in_array('verifie', $values, true)
            || in_array('vérifié', $values, true)
            || in_array('approved', $values, true)
            || filled($profile->onboarding_completed_at ?? null)
        ) {
            return 'verified';
        }

        if (
            in_array('ready', $values, true)
            || in_array('ready_for_review', $values, true)
            || in_array('submitted', $values, true)
            || in_array('pending_review', $values, true)
        ) {
            return 'ready';
        }

        if (
            in_array('in_progress', $values, true)
            || in_array('started', $values, true)
            || in_array('draft', $values, true)
            || in_array('pending', $values, true)
            || in_array('unverified', $values, true)
        ) {
            return 'in_progress';
        }

        return 'in_progress';
    }
}
