<?php

namespace App\Livewire\Admin\ContractsV2;

use App\Models\ContractDocument;
use App\Models\ContractSignature;
use App\Models\ContractTemplate;
use App\Services\ContractsV2\ContractService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class ContractsCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'templates';   // templates | documents | signatures
    public string $search = '';
    public string $filterStatus = '';
    public string $filterType = '';

    public function invalidate(int $signatureId, string $reason = 'Invalidated via admin UI'): void
    {
        $sig = ContractSignature::findOrFail($signatureId);
        try {
            app(ContractService::class)->invalidateSignature($sig, Auth::user(), $reason);
            $this->dispatch('toast', 'Signature invalidée.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $kpis = [
            'templates_active' => ContractTemplate::query()->active()->count(),
            'documents_pending' => ContractDocument::query()->where('status', ContractDocument::STATUS_PENDING_SIGNATURE)->count(),
            'signatures_valid' => ContractSignature::query()->valid()->count(),
            'signatures_invalidated' => ContractSignature::query()->where('is_invalidated', true)->count(),
        ];

        if ($this->tab === 'templates') {
            $items = ContractTemplate::query()
                ->when($this->filterType, fn ($q) => $q->where('type', $this->filterType))
                ->when($this->search, fn ($q) => $q->where(function ($w) {
                    $term = '%' . $this->search . '%';
                    $w->where('code', 'like', $term)->orWhere('name', 'like', $term);
                }))
                ->orderBy('code')
                ->orderByDesc('version')
                ->paginate(20);
        } elseif ($this->tab === 'documents') {
            $items = ContractDocument::query()
                ->with(['template:id,code,name,type', 'user:id,email,name'])
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->orderByDesc('generated_at')
                ->paginate(25);
        } else {
            $items = ContractSignature::query()
                ->with(['document.template:id,code,name', 'signer:id,email'])
                ->orderByDesc('signed_at')
                ->paginate(25);
        }

        return view('livewire.admin.contracts-v2.contracts-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
