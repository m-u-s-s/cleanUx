<?php

namespace App\Livewire\Admin\Payments;

use App\Jobs\Payments\ProcessStripeWebhookJob;
use App\Models\StripeReconciliationRun;
use App\Models\StripeWebhookEvent;
use App\Services\Payments\StripeReconciliationService;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class StripeHardeningCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'webhooks';
    public string $statusFilter = '';
    public ?string $reconcileFromDate = null;
    public ?string $reconcileToDate = null;
    public string $reconcileScope = 'all';

    public function mount(): void
    {
        $this->reconcileFromDate = now()->subDays(7)->toDateString();
        $this->reconcileToDate = now()->toDateString();
    }

    public function retryEvent(int $eventId): void
    {
        $event = StripeWebhookEvent::findOrFail($eventId);

        if (! $event->canRetry() && $event->status !== StripeWebhookEvent::STATUS_DEAD_LETTER) {
            $this->dispatch('toast', 'Cet event ne peut pas être relancé.', 'error');
            return;
        }

        $event->update([
            'status' => StripeWebhookEvent::STATUS_RECEIVED,
            'next_retry_at' => null,
            'last_error' => null,
        ]);

        ProcessStripeWebhookJob::dispatch($event->id)->onQueue('stripe-webhooks');

        ActivityLogger::log('stripe.webhook_event_manual_retry', $event, [
            'admin_user_id' => Auth::id(),
        ]);

        $this->dispatch('toast', 'Event remis en queue.', 'success');
    }

    public function markIgnored(int $eventId): void
    {
        $event = StripeWebhookEvent::findOrFail($eventId);
        $event->update([
            'status' => StripeWebhookEvent::STATUS_IGNORED,
            'processed_at' => now(),
        ]);
        ActivityLogger::log('stripe.webhook_event_manual_ignored', $event, [
            'admin_user_id' => Auth::id(),
        ]);
        $this->dispatch('toast', 'Event marqué ignoré.', 'success');
    }

    public function runReconciliation(): void
    {
        $this->validate([
            'reconcileFromDate' => ['required', 'date'],
            'reconcileToDate' => ['required', 'date', 'after_or_equal:reconcileFromDate'],
            'reconcileScope' => ['required', 'in:all,payment_intents,payouts'],
        ]);

        try {
            $run = app(StripeReconciliationService::class)->run(
                $this->reconcileScope,
                Carbon::parse($this->reconcileFromDate)->startOfDay(),
                Carbon::parse($this->reconcileToDate)->endOfDay(),
                Auth::user(),
            );

            $this->dispatch('toast', sprintf(
                "Réconciliation terminée : %d items, %d écart(s).",
                $run->items_checked,
                $run->mismatches_found,
            ), $run->mismatches_found > 0 ? 'warning' : 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur réconciliation : ' . $e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $kpis = [
            'events_received_24h' => StripeWebhookEvent::query()
                ->where('received_at', '>=', now()->subDay())->count(),
            'events_failed' => StripeWebhookEvent::query()
                ->where('status', StripeWebhookEvent::STATUS_FAILED)->count(),
            'events_dead_letter' => StripeWebhookEvent::query()
                ->where('status', StripeWebhookEvent::STATUS_DEAD_LETTER)->count(),
            'last_reconciliation' => StripeReconciliationRun::query()
                ->latest('started_at')->first(),
        ];

        if ($this->tab === 'webhooks') {
            $items = StripeWebhookEvent::query()
                ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
                ->latest('received_at')
                ->paginate(20);
            $view = 'webhooks';
        } elseif ($this->tab === 'reconciliation') {
            $items = StripeReconciliationRun::query()
                ->with('triggeredBy:id,name')
                ->latest('started_at')
                ->paginate(15);
            $view = 'reconciliation';
        } else {
            $items = StripeWebhookEvent::query()
                ->whereIn('status', [
                    StripeWebhookEvent::STATUS_FAILED,
                    StripeWebhookEvent::STATUS_DEAD_LETTER,
                ])
                ->latest('received_at')
                ->paginate(15);
            $view = 'failures';
        }

        return view('livewire.admin.payments.stripe-hardening-center', [
            'kpis' => $kpis,
            'items' => $items,
            'currentView' => $view,
        ]);
    }
}
