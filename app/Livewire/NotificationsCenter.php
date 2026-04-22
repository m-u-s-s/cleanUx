<?php

namespace App\Livewire;

use App\Support\Notifications\NotificationPresenter;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class NotificationsCenter extends Component
{
    use WithPagination;

    public string $filter = 'all';
    public string $type = 'all';
    public string $search = '';

    protected $queryString = [
        'filter' => ['except' => 'all'],
        'type' => ['except' => 'all'],
        'search' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function markAsRead(string $notificationId): void
    {
        $notification = Auth::user()?->notifications()->whereKey($notificationId)->first();

        if ($notification && is_null($notification->read_at)) {
            $notification->markAsRead();
        }
    }

    public function markAsUnread(string $notificationId): void
    {
        $notification = Auth::user()?->notifications()->whereKey($notificationId)->first();

        if ($notification && ! is_null($notification->read_at)) {
            $notification->forceFill(['read_at' => null])->save();
        }
    }

    public function deleteNotification(string $notificationId): void
    {
        $notification = Auth::user()?->notifications()->whereKey($notificationId)->first();

        if ($notification) {
            $notification->delete();
        }
    }

    public function markAllAsRead(): void
    {
        Auth::user()?->unreadNotifications->markAsRead();
    }

    public function getUnreadCountProperty(): int
    {
        return Auth::user()?->unreadNotifications()->count() ?? 0;
    }

    public function typeOptions(): array
    {
        return [
            'all' => __('Tout'),
            'rendezvous' => __('Rendez-vous'),
            'feedback' => __('Feedback'),
            'finance' => __('Finance'),
            'calendar' => __('Agenda'),
            'admin' => __('Admin'),
            'urgent' => __('Urgent'),
            'system' => __('Système'),
        ];
    }

    protected function filteredNotifications(): Collection
    {
        $user = Auth::user();
        $presenter = app(NotificationPresenter::class);

        if (! $user) {
            return collect();
        }

        $notifications = $user->notifications()->latest()->take(250)->get();

        if ($this->filter === 'unread') {
            $notifications = $notifications->whereNull('read_at');
        } elseif ($this->filter === 'read') {
            $notifications = $notifications->whereNotNull('read_at');
        }

        if ($this->type !== 'all') {
            $notifications = $notifications->filter(fn ($notification) => $presenter->typeKey($notification) === $this->type);
        }

        if ($this->search !== '') {
            $term = mb_strtolower(trim($this->search));
            $notifications = $notifications->filter(fn ($notification) => str_contains($presenter->searchableText($notification), $term));
        }

        return $notifications->values();
    }

    protected function paginateCollection(Collection $items, int $perPage = 15): LengthAwarePaginator
    {
        $page = max(1, (int) $this->getPage());
        $sliced = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $sliced,
            $items->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    public function render()
    {
        $presenter = app(NotificationPresenter::class);
        $notifications = $this->paginateCollection($this->filteredNotifications(), 15);

        return view('livewire.notifications-center', [
            'notifications' => $notifications,
            'unreadCount' => $this->unreadCount,
            'presenter' => $presenter,
            'typeOptions' => $this->typeOptions(),
        ])->layout('layouts.app');
    }
}
