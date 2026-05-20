<?php

namespace App\Livewire\Client;

use App\Models\BusinessDocument;
use App\Models\BusinessEntity;
use App\Services\KybV2\BusinessOnboardingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Wizard d'onboarding KYB B2B en 3 étapes :
 *   1. Informations légales (nom, pays, identifiant, TVA, adresse)
 *   2. Upload documents (Kbis, articles, etc.)
 *   3. Vérifications + récapitulatif
 */
class ClientKybOnboarding extends Component
{
    use WithFileUploads;

    public int $step = 1;
    public ?int $entityId = null;

    // Step 1 fields
    public string $legalName = '';
    public string $tradeName = '';
    public string $countryCode = 'BE';
    public string $identifierType = 'kbo';
    public string $identifierValue = '';
    public string $vatId = '';
    public string $legalForm = '';
    public string $addressStreet = '';
    public string $addressPostal = '';
    public string $addressCity = '';

    // Step 2 — upload
    public ?string $documentType = 'kbis';
    public $documentFile = null;

    public function mount(): void
    {
        // Si une entity existe déjà pour cet user, on saute en step 2
        $existing = BusinessEntity::query()
            ->where('owner_user_id', Auth::id())
            ->latest()
            ->first();
        if ($existing) {
            $this->entityId = $existing->id;
            $this->step = $existing->isVerified() ? 3 : 2;
        }
    }

    public function nextFromStep1(): void
    {
        $this->validate([
            'legalName' => 'required|string|max:255',
            'countryCode' => 'required|string|size:2',
            'identifierType' => 'required|string|max:24',
            'identifierValue' => 'required|string|max:64',
            'vatId' => 'nullable|string|max:32',
            'legalForm' => 'nullable|string|max:64',
        ]);

        try {
            $entity = app(BusinessOnboardingService::class)->startVerification([
                'legal_name' => $this->legalName,
                'trade_name' => $this->tradeName ?: null,
                'country_code' => strtoupper($this->countryCode),
                'identifier_type' => $this->identifierType,
                'identifier_value' => $this->identifierValue,
                'vat_id' => $this->vatId ?: null,
                'legal_form' => $this->legalForm ?: null,
                'registered_address' => $this->addressStreet ? [
                    'street' => $this->addressStreet,
                    'postal' => $this->addressPostal,
                    'city' => $this->addressCity,
                    'country' => strtoupper($this->countryCode),
                ] : null,
            ], Auth::user());

            $this->entityId = $entity->id;
            $this->step = 2;
            $this->dispatch('toast', 'Entité enregistrée. Téléchargez maintenant vos documents.', 'success');
        } catch (ValidationException $e) {
            $this->dispatch('toast', 'Erreur : ' . implode(' / ', collect($e->errors())->flatten()->all()), 'error');
        }
    }

    public function uploadDocument(): void
    {
        if (! $this->entityId) {
            $this->dispatch('toast', 'Étape 1 requise d\'abord.', 'error');
            return;
        }
        $allowedTypes = (array) config('kyb_v2.document_types', []);
        $allowedMimes = (array) config('kyb_v2.allowed_mime_types', []);
        $maxKb = (int) config('kyb_v2.document_max_size_kb', 10240);

        $this->validate([
            'documentType' => 'required|string|in:' . implode(',', $allowedTypes),
            'documentFile' => 'required|file|max:' . $maxKb,
        ]);

        if ($this->documentFile && ! empty($allowedMimes) && ! in_array($this->documentFile->getMimeType(), $allowedMimes, true)) {
            $this->dispatch('toast', 'Type MIME non autorisé.', 'error');
            return;
        }

        $disk = (string) config('kyb_v2.document_storage_disk', 'local');
        $prefix = trim((string) config('kyb_v2.document_path_prefix', 'kyb_documents'), '/');
        $name = uniqid('doc_', true) . '_' . preg_replace('/[^a-z0-9_.-]+/i', '_', $this->documentFile->getClientOriginalName());
        $path = $prefix . '/' . date('Y/m/d') . '/entity-' . $this->entityId . '/' . $name;
        Storage::disk($disk)->put($path, file_get_contents($this->documentFile->getRealPath()));

        BusinessDocument::query()->create([
            'entity_id' => $this->entityId,
            'document_type' => $this->documentType,
            'file_path' => $path,
            'mime_type' => $this->documentFile->getMimeType(),
            'size_bytes' => (int) $this->documentFile->getSize(),
            'uploaded_at' => now(),
            'uploaded_by_user_id' => Auth::id(),
            'status' => BusinessDocument::STATUS_PENDING,
        ]);

        $this->documentFile = null;
        $this->dispatch('toast', 'Document uploadé. Notre équipe va le vérifier.', 'success');
    }

    public function triggerVerifications(): void
    {
        if (! $this->entityId) {
            return;
        }
        $entity = BusinessEntity::find($this->entityId);
        if (! $entity || $entity->owner_user_id !== Auth::id()) {
            return;
        }
        app(BusinessOnboardingService::class)->runVerifications($entity);
        app(BusinessOnboardingService::class)->runSanctionsScreening($entity);
        $this->step = 3;
        $this->dispatch('toast', 'Vérifications lancées. Statut mis à jour.', 'success');
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step <= 3) {
            $this->step = $step;
        }
    }

    #[Computed]
    public function entity(): ?BusinessEntity
    {
        return $this->entityId ? BusinessEntity::find($this->entityId) : null;
    }

    #[Computed]
    public function documents()
    {
        return $this->entityId
            ? BusinessDocument::query()->where('entity_id', $this->entityId)->orderByDesc('uploaded_at')->get()
            : collect();
    }

    public function render(): View
    {
        return view('livewire.client.client-kyb-onboarding');
    }
}
