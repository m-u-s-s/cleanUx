<?php

namespace App\Livewire\Admin\Onboarding;

use App\Models\ProviderOnboardingDocument;
use App\Services\Onboarding\ProviderOnboardingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
                $term = '%' . trim($this->search) . '%';
                $q->whereHas('user', function ($u) use ($term) {
                    $u->where('name', 'like', $term)
                      ->orWhere('email', 'like', $term);
                });
            })
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    public function getCountsProperty(): array
    {
        return [
            'pending'  => ProviderOnboardingDocument::where('status', 'pending_review')->count(),
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
        if (! $this->previewingDocumentId) return null;
        return ProviderOnboardingDocument::with('user:id,name,email')
            ->find($this->previewingDocumentId);
    }

    /**
     * Génère une URL signée temporaire pour télécharger/voir le fichier privé.
     */
    public function getPreviewUrlProperty(): ?string
    {
        $doc = $this->previewDocument;
        if (! $doc) return null;

        // Route admin signée (à déclarer dans routes/admin.php — voir patches)
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'admin.onboarding.document.file',
            now()->addMinutes(10),
            ['document' => $doc->id],
        );
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        return view('livewire.admin.onboarding.admin-onboarding-documents-center', [
            'documents' => $this->documents,
            'counts'    => $this->counts,
        ]);
    }
}
