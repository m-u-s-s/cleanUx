<?php

namespace App\Livewire;

use App\Models\User;
use App\Support\Notifications\NotificationPresenter;
use Illuminate\Contracts\View\View;
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

    protected function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

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
        $user = $this->currentUser();

        if (! $user) {
            return;
        }

        $notification = $user->notifications()->whereKey($notificationId)->first();

        if ($notification && is_null($notification->read_at)) {
            $notification->markAsRead();
        }
    }

    public function markAsUnread(string $notificationId): void
    {
        $user = $this->currentUser();

        if (! $user) {
            return;
        }

        $notification = $user->notifications()->whereKey($notificationId)->first();

        if ($notification && ! is_null($notification->read_at)) {
            $notification->forceFill(['read_at' => null])->save();
        }
    }

    public function deleteNotification(string $notificationId): void
    {
        $user = $this->currentUser();

        if (! $user) {
            return;
        }

        $notification = $user->notifications()->whereKey($notificationId)->first();

        if ($notification) {
            $notification->delete();
        }
    }

    public function markAllAsRead(): void
    {
        $user = $this->currentUser();

        if (! $user) {
            return;
        }

        $user->unreadNotifications->markAsRead();
    }

    public function getUnreadCountProperty(): int
    {
        $user = $this->currentUser();

        return $user?->unreadNotifications()->count() ?? 0;
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
        $user = $this->currentUser();
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
            $notifications = $notifications->filter(
                fn ($notification) => $presenter->typeKey($notification) === $this->type
            );
        }

        if ($this->search !== '') {
            $term = mb_strtolower(trim($this->search));
            $notifications = $notifications->filter(
                fn ($notification) => str_contains($presenter->searchableText($notification), $term)
            );
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

    public function render(): View
    {
        $presenter = app(NotificationPresenter::class);
        $notifications = $this->paginateCollection($this->filteredNotifications(), 15);

        return view('livewire.notifications-center', [
            'notifications' => $notifications,
            'unreadCount' => $this->unreadCount,
            'presenter' => $presenter,
            'typeOptions' => $this->typeOptions(),
        ]);
    }
}