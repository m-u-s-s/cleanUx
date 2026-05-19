<?php

namespace App\Livewire\Provider;

use App\Models\ComplaintCase;
use App\Models\DisputeEvent;
use App\Services\Disputes\DisputeService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class ProviderDisputesPage extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public ?int $selectedId = null;
    public string $responseBody = '';
    public string $filterStatus = '';

    public function select(int $id): void
    {
        $case = ComplaintCase::query()
            ->where('provider_user_id', Auth::id())
            ->find($id);

        if (! $case) {
            $this->dispatch('toast', 'Litige introuvable.', 'error');
            return;
        }

        $this->selectedId = $case->id;
        $this->responseBody = '';
    }

    public function postResponse(): void
    {
        $this->validate(['responseBody' => ['required', 'string', 'min:1', 'max:2000']]);

        $case = ComplaintCase::query()
            ->where('id', $this->selectedId)
            ->where('provider_user_id', Auth::id())
            ->firstOrFail();

        try {
            app(DisputeService::class)->addMessage(
                $case,
                Auth::user(),
                DisputeEvent::ROLE_PROVIDER,
                $this->responseBody,
            );
            $this->reset(['responseBody']);
            $this->dispatch('toast', 'Réponse envoyée au support.', 'success');
        } catch (ValidationException $e) {
            $this->addError('responseBody', collect($e->errors())->flatten()->first());
        }
    }

    public function render(): View
    {
        $providerId = Auth::id();

        $list = ComplaintCase::query()
            ->where('provider_user_id', $providerId)
            ->with(['client:id,name', 'booking:id,booking_reference'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->latest('last_activity_at')
            ->latest('id')
            ->paginate(10);

        $selected = $this->selectedId
            ? ComplaintCase::query()
                ->where('provider_user_id', $providerId)
                ->with([
                    'client:id,name',
                    'booking:id,booking_reference',
                    'events' => fn ($q) => $q->visibleTo(DisputeEvent::ROLE_PROVIDER)->orderBy('created_at'),
                    'events.author:id,name',
                    'resolutions',
                ])
                ->find($this->selectedId)
            : null;

        return view('livewire.provider.provider-disputes-page', [
            'list' => $list,
            'selected' => $selected,
        ]);
    }
}
