<?php

namespace App\Livewire\Admin\Sms;

use App\Models\SmsMessage;
use App\Services\Notifications\SmsService;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class SmsCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $tab = 'recent';
    public string $filterStatus = '';
    public string $filterProvider = '';
    public string $filterCategory = '';
    public string $search = '';

    public function retry(int $messageId): void
    {
        $message = SmsMessage::findOrFail($messageId);

        if (! in_array($message->status, [SmsMessage::STATUS_FAILED, SmsMessage::STATUS_UNDELIVERED, SmsMessage::STATUS_RATE_LIMITED], true)) {
            $this->dispatch('toast', 'Seuls les SMS failed/undelivered/rate_limited peuvent être retentés.', 'error');
            return;
        }

        try {
            app(SmsService::class)->dispatch(
                toPhone: $message->to_phone,
                body: $message->body,
                user: $message->user,
                category: $message->category ?? SmsMessage::CATEGORY_TRANSACTIONAL,
                idempotencyKey: 'retry:' . $message->id . ':' . now()->timestamp,
                locale: $message->locale,
            );

            ActivityLogger::log('sms.manual_retry', $message, [
                'admin_user_id' => Auth::id(),
            ]);

            $this->dispatch('toast', 'SMS re-envoyé.', 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', 'Erreur retry : ' . $e->getMessage(), 'error');
        }
    }

    public function render(): View
    {
        $kpis = [
            'total_24h' => SmsMessage::query()->where('queued_at', '>=', now()->subDay())->count(),
            'delivered_24h' => SmsMessage::query()
                ->where('status', SmsMessage::STATUS_DELIVERED)
                ->where('queued_at', '>=', now()->subDay())->count(),
            'failed_24h' => SmsMessage::query()
                ->whereIn('status', [SmsMessage::STATUS_FAILED, SmsMessage::STATUS_UNDELIVERED])
                ->where('queued_at', '>=', now()->subDay())->count(),
            'rate_limited_24h' => SmsMessage::query()
                ->where('status', SmsMessage::STATUS_RATE_LIMITED)
                ->where('queued_at', '>=', now()->subDay())->count(),
        ];

        $items = SmsMessage::query()
            ->with('user:id,name,email')
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterProvider, fn ($q) => $q->where('provider', $this->filterProvider))
            ->when($this->filterCategory, fn ($q) => $q->where('category', $this->filterCategory))
            ->when($this->search, function ($q) {
                $term = '%' . $this->search . '%';
                $q->where(function ($inner) use ($term) {
                    $inner->where('to_phone', 'like', $term)
                        ->orWhere('body', 'like', $term)
                        ->orWhere('external_id', 'like', $term)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term));
                });
            })
            ->latest('queued_at')
            ->paginate(20);

        return view('livewire.admin.sms.sms-center', [
            'kpis' => $kpis,
            'items' => $items,
        ]);
    }
}
