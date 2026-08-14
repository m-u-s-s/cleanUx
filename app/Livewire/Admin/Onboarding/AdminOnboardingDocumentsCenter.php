<?php

namespace App\Livewire\Admin\Onboarding;

use App\Models\FleetVehicle;
use App\Models\ProviderOnboardingDocument;
use App\Services\Onboarding\ProviderOnboardingService;
use App\Services\Onboarding\ProviderVehicleService;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Phase 14.1 — Centre admin de validation des documents KYC.
 *
 * Route : /admin/onboarding-documents
 *
 * Affiche :
 *   - Counts (pending / approved / rejected)
 *   - Filtres par status, type de doc, recherche provider
 *   - Tableau des documents avec actions approve/reject
 *   - Modal de visualisation du fichier (PDF embed ou image)
 *
 * Utilise ProviderOnboardingService::reviewDocument() pour la persistance.
 */
class AdminOnboardingDocumentsCenter extends Component
{
    use EnforcesAdminAccess;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $search = '';

    public string $filterStatus = 'pending_review';

    public string $filterType = 'all';

    public ?int $reviewingDocumentId = null;

    public string $rejectionReason = '';

    public ?int $previewingDocumentId = null;

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterStatus()
    {
        $this->resetPage();
    }

    public function updatingFilterType()
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->filterStatus = 'pending_review';
        $this->filterType = 'all';
        $this->resetPage();
    }

    // ──────────────────────────────────────────────
    // Computed properties
    // ──────────────────────────────────────────────

    public function getDocumentsProperty()
    {
        return ProviderOnboardingDocument::query()
            ->with(['user:id,name,email,phone', 'reviewer:id,name'])
            ->when($this->filterStatus !== 'all', fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterType !== 'all', fn ($q) => $q->where('document_type', $this->filterType))
            ->when($this->search !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->whereHas('user', function ($u) use ($term) {
                    $u->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    /**
     * LE VÉHICULE DÉCLARÉ, EN FACE DE LA CARTE GRISE À RELIRE.
     *
     * L'administrateur devait juger l'âge du véhicule sur une photo de certificat, en lisant une
     * date en petits caractères et en la soustrayant de tête. Le calcul existe pourtant déjà côté
     * serveur, et c'est LUI qui décide du dispatch : ne pas le montrer ici laissait la revue humaine
     * et le verrou automatique juger séparément — avec la possibilité qu'ils divergent.
     *
     * Chargé pour la PAGE COURANTE seulement, en une requête : un appel par ligne ferait vingt
     * allers-retours pour un écran qu'on parcourt en trois secondes.
     *
     * @param  iterable<int, ProviderOnboardingDocument>  $documents  La page affichée. Passée en
     *                                                                paramètre plutôt que relue :
     *                                                                la requête est paginée et
     *                                                                filtrée, la rejouer ici
     *                                                                pourrait rendre une autre page.
     * @return array<int, array<string, mixed>>
     */
    public function vehiculesDes(iterable $documents): array
    {
        $userIds = collect($documents)
            ->filter(fn (ProviderOnboardingDocument $doc): bool => $doc->document_type === ProviderOnboardingDocument::TYPE_VEHICLE_REGISTRATION)
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        $service = app(ProviderVehicleService::class);

        return FleetVehicle::query()
            ->whereIn('current_provider_id', $userIds)
            ->whereNot('status', FleetVehicle::STATUS_RETIRED)
            ->get()
            ->keyBy('current_provider_id')
            ->map(fn (FleetVehicle $vehicule) => [
                'plate' => $vehicule->plate,
                'label' => trim(($vehicule->brand ?? '').' '.($vehicule->model ?? '')) ?: null,
                'registered_at' => $vehicule->registered_at?->format('d/m/Y'),
                'age' => $service->ageEnAnnees($vehicule),
                'limite' => $service->limiteDAge($vehicule->registered_country),
            ])
            ->all();
    }

    public function getCountsProperty(): array
    {
        return [
            'pending' => ProviderOnboardingDocument::where('status', 'pending_review')->count(),
            'approved' => ProviderOnboardingDocument::where('status', 'approved')->count(),
            'rejected' => ProviderOnboardingDocument::where('status', 'rejected')->count(),
        ];
    }

    // ──────────────────────────────────────────────
    // Actions
    // ──────────────────────────────────────────────

    public function approve(int $documentId): void
    {
        $document = ProviderOnboardingDocument::find($documentId);
        if (! $document) {
            session()->flash('error', 'Document introuvable.');

            return;
        }

        app(ProviderOnboardingService::class)->reviewDocument(
            $document,
            Auth::user(),
            true,
        );

        session()->flash('success', 'Document approuvé.');
        $this->reviewingDocumentId = null;
        $this->rejectionReason = '';
    }

    public function openRejectModal(int $documentId): void
    {
        $this->reviewingDocumentId = $documentId;
        $this->rejectionReason = '';
    }

    public function closeRejectModal(): void
    {
        $this->reviewingDocumentId = null;
        $this->rejectionReason = '';
    }

    public function reject(): void
    {
        $this->validate([
            'rejectionReason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $document = ProviderOnboardingDocument::find($this->reviewingDocumentId);
        if (! $document) {
            session()->flash('error', 'Document introuvable.');
            $this->closeRejectModal();

            return;
        }

        app(ProviderOnboardingService::class)->reviewDocument(
            $document,
            Auth::user(),
            false,
            $this->rejectionReason,
        );

        session()->flash('success', 'Document rejeté avec motif.');
        $this->closeRejectModal();
    }

    public function preview(int $documentId): void
    {
        $this->previewingDocumentId = $documentId;
    }

    public function closePreview(): void
    {
        $this->previewingDocumentId = null;
    }

    public function getPreviewDocumentProperty(): ?ProviderOnboardingDocument
    {
        if (! $this->previewingDocumentId) {
            return null;
        }

        return ProviderOnboardingDocument::with('user:id,name,email')
            ->find($this->previewingDocumentId);
    }

    /**
     * Génère une URL signée temporaire pour télécharger/voir le fichier privé.
     */
    public function getPreviewUrlProperty(): ?string
    {
        $doc = $this->previewDocument;
        if (! $doc) {
            return null;
        }

        // Route admin signée (à déclarer dans routes/admin.php — voir patches)
        return URL::temporarySignedRoute(
            'admin.onboarding.document.file',
            now()->addMinutes(10),
            ['document' => $doc->id],
        );
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        $documents = $this->documents;

        return view('livewire.admin.onboarding.admin-onboarding-documents-center', [
            'documents' => $documents,
            'counts' => $this->counts,
            // Résolus pour la page affichée, en une requête : un appel par ligne ferait vingt
            // allers-retours sur un écran qu'on parcourt en trois secondes.
            'vehicules' => $this->vehiculesDes($documents->items()),
        ]);
    }
}
