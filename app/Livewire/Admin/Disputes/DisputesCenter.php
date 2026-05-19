<?php

namespace App\Livewire\Admin\Disputes;

use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Models\DisputeResolution;
use App\Models\User;
use App\Services\Disputes\DisputeResolutionService;
use App\Services\Disputes\DisputeService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class DisputesCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $filterStatus = '';
    public string $filterPriority = '';
    public string $filterCategory = '';
    public bool $showOverdueOnly = false;
    public string $search = '';

    public ?int $selectedId = null;

    public string $messageBody = '';
    public string $messageVisibility = DisputeEvent::VISIBILITY_ALL;

    public string $resolutionType = DisputeResolution::TYPE_REFUND_FULL;
    public ?float $resolutionAmount = null;
    public string $resolutionExplanation = '';

    public function select(int $id): void
    {
        $this->selectedId = $id;
        $this->reset(['messageBody', 'resolutionAmount', 'resolutionExplanation']);
    }

    public function closeDetail(): void
    {
        $this->reset(['selectedId', 'messageBody', 'resolutionAmount', 'resolutionExplanation']);
    }

    public function assignToMe(): void
    {
        $case = ComplaintCase::findOrFail($this->selectedId);
        app(DisputeService::class)->assign($case, Auth::user());
        $this->dispatch('toast', 'Dispute assignée à vous.', 'success');
    }

    public function transitionTo(string $status): void
    {
        $case = ComplaintCase::findOrFail($this->selectedId);
        app(DisputeService::class)->transition($case, $status, Auth::user());
        $this->dispatch('toast', 'Statut mis à jour.', 'success');
    }

    public function escalate(): void
    {
        $case = ComplaintCase::findOrFail($this->selectedId);
        app(DisputeService::class)->escalate($case, 'Escalade manuelle admin');
        $this->dispatch('toast', 'Dispute escaladée.', 'success');
    }

    public function postMessage(): void
    {
        $this->validate([
            'messageBody' => ['required', 'string', 'min:1', 'max:5000'],
            'messageVisibility' => ['required', 'in:all,client,provider,private'],
        ]);

        $case = ComplaintCase::findOrFail($this->selectedId);
        app(DisputeService::class)->addMessage(
            $case,
            Auth::user(),
            DisputeEvent::ROLE_ADMIN,
            $this->messageBody,
            $this->messageVisibility,
        );

        $this->reset(['messageBody']);
        $this->dispatch('toast', 'Message ajouté.', 'success');
    }

    public function applyResolution(): void
    {
        $needsAmount = in_array($this->resolutionType, [
            DisputeResolution::TYPE_REFUND_PARTIAL,
            DisputeResolution::TYPE_CREDIT,
            DisputeResolution::TYPE_PROMO_CODE,
        ], true);

        $this->validate(array_filter([
            'resolutionType' => ['required', 'string'],
            'resolutionExplanation' => ['required', 'string', 'min:5', 'max:2000'],
            'resolutionAmount' => $needsAmount ? ['required', 'numeric', 'min:0.01'] : ['nullable', 'numeric'],
        ]));

        $case = ComplaintCase::findOrFail($this->selectedId);

        try {
            app(DisputeResolutionService::class)->apply($case, Auth::user(), [
                'resolution_type' => $this->resolutionType,
                'amount' => $this->resolutionAmount,
                'explanation' => $this->resolutionExplanation,
            ]);

            $this->reset(['resolutionAmount', 'resolutionExplanation']);
            $this->dispatch('toast', 'Résolution appliquée.', 'success');
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $msg) {
                    $this->addError($field, $msg);
                }
            }
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur : ' . $e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $kpis = [
            'open' => ComplaintCase::query()->active()->count(),
            'overdue' => ComplaintCase::query()->overdue()->count(),
            'escalated' => ComplaintCase::query()
                ->where('status', ComplaintCase::STATUS_ESCALATED)
                ->orWhere('escalation_level', '>', 0)
                ->count(),
            'resolved_today' => ComplaintCase::query()
                ->where('status', ComplaintCase::STATUS_RESOLVED)
                ->whereDate('resolved_at', today())
                ->count(),
        ];

        $list = ComplaintCase::query()
            ->with(['client:id,name,email', 'provider:id,name', 'assignee:id,name'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterPriority, fn ($q) => $q->where('priority', $this->filterPriority))
            ->when($this->filterCategory, fn ($q) => $q->where('category', $this->filterCategory))
            ->when($this->showOverdueOnly, fn ($q) => $q->overdue())
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('reference', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhereHas('client', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->latest('last_activity_at')
            ->latest('id')
            ->paginate(15);

        $selected = $this->selectedId
            ? ComplaintCase::query()
                ->with([
                    'client:id,name,email',
                    'provider:id,name',
                    'assignee:id,name',
                    'booking:id,booking_reference,date,heure',
                    'events.author:id,name',
                    'resolutions',
                ])
                ->find($this->selectedId)
            : null;

        $admins = User::query()
            ->where('role', 'admin')
            ->orWhere('platform_role', 'admin')
            ->orWhere('platform_role', 'super_admin')
            ->select(['id', 'name'])
            ->limit(50)
            ->get();

        return view('livewire.admin.disputes.disputes-center', [
            'kpis' => $kpis,
            'list' => $list,
            'selected' => $selected,
            'admins' => $admins,
        ]);
    }
}
