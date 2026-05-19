<?php

namespace App\Livewire\Admin\Push;

use App\Models\DeviceToken;
use App\Models\PushNotification;
use App\Services\Push\PushService;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class PushCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $filterStatus = '';
    public string $filterProvider = '';
    public string $filterCategory = '';
    public string $search = '';

    public function retry(int $notificationId): void
    {
        $notif = PushNotification::findOrFail($notificationId);

        if (! in_array($notif->status, [
            PushNotification::STATUS_FAILED,
            PushNotification::STATUS_RATE_LIMITED,
        ], true)) {
            $this->dispatch('toast', 'Seuls les push failed/rate_limited peuvent être retentés.', 'error');
            return;
        }

        if (! $notif->deviceToken || ! $notif->deviceToken->isActive()) {
            $this->dispatch('toast', 'Le device token est invalide.', 'error');
            return;
        }

        try {
            app(PushService::class)->dispatch(
                token: $notif->deviceToken,
                title: $notif->title,
                body: $notif->body,
                data: $notif->data ?? [],
                category: $notif->category ?? PushNotification::CATEGORY_TRANSACTIONAL,
                idempotencyKey: 'retry:' . $notif->id . ':' . now()->timestamp,
                locale: $notif->locale,
            );

            ActivityLogger::log('push.manual_retry', $notif, [
                'admin_user_id' => Auth::id(),
            ]);

            $this->dispatch('toast', 'Push re-envoyé.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur retry : ' . $e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $kpis = [
            'total_24h' => PushNotification::query()
                ->where('queued_at', '>=', now()->subDay())->count(),
            'delivered_24h' => PushNotification::query()
                ->whereIn('status', [PushNotification::STATUS_SENT, PushNotification::STATUS_DELIVERED])
                ->where('queued_at', '>=', now()->subDay())->count(),
            'failed_24h' => PushNotification::query()
                ->whereIn('status', [PushNotification::STATUS_FAILED, PushNotification::STATUS_INVALID_TOKEN])
                ->where('queued_at', '>=', now()->subDay())->count(),
            'opted_out_24h' => PushNotification::query()
                ->where('status', PushNotification::STATUS_OPTED_OUT)
                ->where('queued_at', '>=', now()->subDay())->count(),
            'active_tokens' => DeviceToken::query()->active()->count(),
        ];

        $items = PushNotification::query()
            ->with(['user:id,name,email', 'deviceToken:id,platform,provider'])
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterProvider, fn ($q) => $q->where('provider', $this->filterProvider))
            ->when($this->filterCategory, fn ($q) => $q->where('category', $this->filterCategory))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', $term)
                        ->orWhere('body', 'like', $term)
                        ->orWhere('external_id', 'like', $term)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->latest('queued_at')
            ->paginate(20);

        return view('livewire.admin.push.push-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
