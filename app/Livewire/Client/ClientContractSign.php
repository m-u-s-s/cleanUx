<?php

namespace App\Livewire\Client;

use App\Models\ContractDocument;
use App\Models\ContractSignature;
use App\Models\ContractTemplate;
use App\Services\ContractsV2\ContractService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class ClientContractSign extends Component
{
    public ?int $documentId = null;

    // Form fields
    public string $signerName = '';
    public string $signatureData = '';   // base64 PNG du canvas pad
    public bool $termsAccepted = false;
    public string $countryCode = '';

    public function mount(?int $documentId = null): void
    {
        $this->documentId = $documentId;
        $user = Auth::user();
        $this->signerName = trim((string) ($user?->name ?? ''));
    }

    public function selectDocument(int $documentId): void
    {
        $doc = ContractDocument::query()->find($documentId);
        if (! $doc || ($doc->user_id && $doc->user_id !== Auth::id())) {
            $this->dispatch('toast', 'Document inaccessible.', 'error');
            return;
        }
        $this->documentId = $doc->id;
        // Audit "opened"
        app(ContractService::class)->audit($doc, 'opened', request());
    }

    public function renderFromTemplate(string $templateCode): void
    {
        try {
            $doc = app(ContractService::class)->renderDocumentFor($templateCode, Auth::user());
            $this->documentId = $doc->id;
            $this->dispatch('toast', 'Contrat généré, prêt à signer.', 'success');
        } catch (ValidationException $e) {
            $this->dispatch('toast', 'Erreur : ' . implode(' / ', collect($e->errors())->flatten()->all()), 'error');
        }
    }

    public function sign(): void
    {
        if (! $this->documentId) {
            $this->dispatch('toast', 'Sélectionnez un contrat d\'abord.', 'error');
            return;
        }
        $this->validate([
            'signerName' => 'required|string|max:191',
            'signatureData' => 'required|string|min:50',
            'termsAccepted' => 'accepted',
            'countryCode' => 'nullable|string|size:2',
        ], [
            'termsAccepted.accepted' => 'Vous devez accepter les conditions.',
            'signatureData.min' => 'Veuillez signer dans la zone prévue.',
        ]);

        $doc = ContractDocument::query()->find($this->documentId);
        if (! $doc || ($doc->user_id && $doc->user_id !== Auth::id())) {
            $this->dispatch('toast', 'Document inaccessible.', 'error');
            return;
        }
        try {
            app(ContractService::class)->signDocument(
                document: $doc,
                signer: Auth::user(),
                signatureData: $this->signatureData,
                signerName: $this->signerName,
                request: request(),
                extraMeta: array_filter(['country_code' => $this->countryCode ?: null]),
            );
            $this->signatureData = '';
            $this->dispatch('toast', 'Contrat signé. Une copie PDF est disponible dans vos documents.', 'success');
        } catch (ValidationException $e) {
            $this->dispatch('toast', 'Erreur : ' . implode(' / ', collect($e->errors())->flatten()->all()), 'error');
        }
    }

    #[Computed]
    public function pendingDocuments()
    {
        return ContractDocument::query()
            ->where('user_id', Auth::id())
            ->where('status', ContractDocument::STATUS_PENDING_SIGNATURE)
            ->with('template:id,code,name,type,version')
            ->orderByDesc('generated_at')
            ->get();
    }

    #[Computed]
    public function signedDocuments()
    {
        return ContractDocument::query()
            ->where('user_id', Auth::id())
            ->where('status', ContractDocument::STATUS_SIGNED)
            ->with('template:id,code,name,type,version')
            ->orderByDesc('generated_at')
            ->limit(10)
            ->get();
    }

    #[Computed]
    public function availableTemplates()
    {
        return ContractTemplate::query()
            ->active()
            ->where('role', '!=', ContractTemplate::ROLE_PROVIDER)
            ->orderBy('code')
            ->limit(10)
            ->get(['id', 'code', 'name', 'description', 'type', 'version']);
    }

    #[Computed]
    public function currentDocument(): ?ContractDocument
    {
        if (! $this->documentId) {
            return null;
        }
        $doc = ContractDocument::query()->with('template')->find($this->documentId);
        if (! $doc || ($doc->user_id && $doc->user_id !== Auth::id())) {
            return null;
        }
        return $doc;
    }

    public function render(): View
    {
        return view('livewire.client.client-contract-sign');
    }
}
