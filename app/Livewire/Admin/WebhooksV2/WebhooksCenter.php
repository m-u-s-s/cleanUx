<?php

namespace App\Livewire\Admin\WebhooksV2;

use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Models\WebhookEvent;
use App\Models\WebhookSubscription;
use App\Services\WebhooksV2\WebhookDispatcher;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\WithPagination;

class WebhooksCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'endpoints';   // endpoints | events | deliveries
    public string $filterStatus = '';
    public ?int $filterEndpointId = null;

    // create endpoint form
    public string $newName = '';
    public string $newUrl = '';
    public array $newEventCodes = [];

    public function rotateSecret(int $endpointId): void
    {
        $ep = WebhookEndpoint::query()->findOrFail($endpointId);
        $ep->update(['secret' => WebhookEndpoint::generateSecret()]);
        $this->dispatch('toast', 'Secret rotated.', 'success');
    }

    public function toggleSuspend(int $endpointId): void
    {
        $ep = WebhookEndpoint::query()->findOrFail($endpointId);
        $ep->update([
            'is_suspended' => ! $ep->is_suspended,
            'suspension_reason' => $ep->is_suspended ? null : 'Suspended via admin UI',
            'consecutive_failures' => $ep->is_suspended ? 0 : $ep->consecutive_failures,
        ]);
        $this->dispatch('toast', 'Endpoint updated.', 'success');
    }

    public function sendTest(int $endpointId): void
    {
        $ep = WebhookEndpoint::query()->findOrFail($endpointId);
        $event = app(WebhookDispatcher::class)->emit(
            eventCode: 'test.ping',
            payload: ['message' => 'admin test', 'fired_at' => now()->toIso8601String()],
        );
        if ($event) {
            $existing = WebhookDelivery::query()
                ->where('event_id', $event->id)
                ->where('endpoint_id', $ep->id)
                ->exists();
            if (! $existing) {
                $delivery = WebhookDelivery::query()->create([
                    'event_id' => $event->id,
                    'endpoint_id' => $ep->id,
                    'status' => WebhookDelivery::STATUS_PENDING,
                    'attempt' => 0,
                    'max_attempts' => $ep->max_attempts,
                ]);
                \App\Jobs\WebhooksV2\DeliverWebhookJob::dispatch($delivery->id)
                    ->onQueue((string) config('webhooks_v2.queue', 'webhooks'));
            }
        }
        $this->dispatch('toast', 'Test ping envoyé.', 'success');
    }

    public function replay(int $deliveryId): void
    {
        $d = WebhookDelivery::query()->findOrFail($deliveryId);
        app(WebhookDispatcher::class)->replay($d);
        $this->dispatch('toast', 'Delivery replayed.', 'success');
    }

    public function createEndpoint(): void
    {
        $allowed = (array) config('webhooks_v2.allowed_events', []);
        try {
            $this->validate([
                'newName' => 'required|string|max:191',
                'newUrl' => 'required|url|max:500',
                'newEventCodes' => 'required|array|min:1',
                'newEventCodes.*' => 'string|in:' . implode(',', $allowed),
            ]);
        } catch (ValidationException $e) {
            $this->dispatch('toast', 'Validation : ' . Str::limit(json_encode($e->errors()), 200), 'error');
            return;
        }
        $ep = WebhookEndpoint::query()->create([
            'code' => WebhookEndpoint::generateCode(),
            'name' => $this->newName,
            'url' => $this->newUrl,
            'secret' => WebhookEndpoint::generateSecret(),
            'is_active' => true,
            'max_attempts' => (int) config('webhooks_v2.max_attempts', 6),
            'timeout_seconds' => (int) config('webhooks_v2.timeout_seconds', 15),
        ]);
        foreach (array_unique($this->newEventCodes) as $code) {
            WebhookSubscription::query()->create([
                'endpoint_id' => $ep->id,
                'event_code' => $code,
                'is_active' => true,
            ]);
        }
        $this->newName = '';
        $this->newUrl = '';
        $this->newEventCodes = [];
        $this->dispatch('toast', 'Endpoint créé.', 'success');
    }

    public function render(): View
    {
        $kpis = [
            'endpoints_active' => WebhookEndpoint::query()->where('is_active', true)->where('is_suspended', false)->count(),
            'endpoints_suspended' => WebhookEndpoint::query()->where('is_suspended', true)->count(),
            'deliveries_dead' => WebhookDelivery::query()->where('status', WebhookDelivery::STATUS_DEAD)->count(),
            'deliveries_pending' => WebhookDelivery::query()->whereIn('status', [
                WebhookDelivery::STATUS_PENDING,
                WebhookDelivery::STATUS_FAILED,
                WebhookDelivery::STATUS_IN_FLIGHT,
            ])->count(),
        ];

        if ($this->tab === 'endpoints') {
            $items = WebhookEndpoint::query()
                ->withCount(['subscriptions', 'deliveries'])
                ->orderByDesc('created_at')
                ->paginate(20);
        } elseif ($this->tab === 'events') {
            $items = WebhookEvent::query()
                ->orderByDesc('id')
                ->paginate(25);
        } else {
            $items = WebhookDelivery::query()
                ->with(['event:id,event_id,event_code', 'endpoint:id,code,name'])
                ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
                ->when($this->filterEndpointId, fn ($q) => $q->where('endpoint_id', $this->filterEndpointId))
                ->orderByDesc('id')
                ->paginate(25);
        }

        $allowedEvents = (array) config('webhooks_v2.allowed_events', []);

        return view('livewire.admin.webhooks-v2.webhooks-center', [
            'kpis' => $kpis,
            'items' => $items,
            'allowedEvents' => $allowedEvents,
        ]);
    }
}
