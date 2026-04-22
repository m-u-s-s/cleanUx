<?php

namespace App\Livewire;

use App\Support\Notifications\NotificationPresenter;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Notifications extends Component
{
    public int $limit = 8;
    public string $status = 'all';
    public string $type = 'all';

    protected $listeners = [
        'notificationCreated' => '$refresh',
    ];

    public function markAsRead(string $notificationId): void
    {
        if (! Auth::check()) {
            return;
        }

        $notification = $this->findNotification($notificationId);

        if ($notification && is_null($notification->read_at)) {
            $notification->markAsRead();
        }
    }

    public function markAsUnread(string $notificationId): void
    {
        if (! Auth::check()) {
            return;
        }

        $notification = $this->findNotification($notificationId);

        if ($notification && ! is_null($notification->read_at)) {
            $notification->forceFill(['read_at' => null])->save();
        }
    }

    public function markAllAsRead(): void
    {
        if (! Auth::check()) {
            return;
        }

        Auth::user()?->unreadNotifications->markAsRead();
    }

    public function deleteNotification(string $notificationId): void
    {
        if (! Auth::check()) {
            return;
        }

        $notification = $this->findNotification($notificationId);

        if ($notification) {
            $notification->delete();
        }
    }

    public function setStatusFilter(string $status): void
    {
        $this->status = in_array($status, ['all', 'unread', 'read'], true) ? $status : 'all';
    }

    public function setTypeFilter(string $type): void
    {
        $this->type = array_key_exists($type, $this->typeOptions()) ? $type : 'all';
    }

    public function loadMore(): void
    {
        $this->limit += 8;
    }

    public function getUnreadCountProperty(): int
    {
        if (! Auth::check()) {
            return 0;
        }

        return Auth::user()->unreadNotifications()->count();
    }

    public function getNotificationsProperty(): Collection
    {
        if (! Auth::check()) {
            return collect();
        }

        $presenter = app(NotificationPresenter::class);

        $notifications = Auth::user()
            ->notifications()
            ->latest()
            ->take(max($this->limit * 4, 40))
            ->get();

        if ($this->status === 'unread') {
            $notifications = $notifications->whereNull('read_at');
        } elseif ($this->status === 'read') {
            $notifications = $notifications->whereNotNull('read_at');
        }

        if ($this->type !== 'all') {
            $notifications = $notifications->filter(fn (DatabaseNotification $notification) => $presenter->typeKey($notification) === $this->type);
        }

        return $notifications->take($this->limit)->values();
    }

    public function notificationType(DatabaseNotification $notification): string
    {
        return app(NotificationPresenter::class)->typeKey($notification);
    }

    public function notificationTypeLabel(DatabaseNotification $notification): string
    {
        return app(NotificationPresenter::class)->label($notification);
    }

    public function notificationActionUrl(DatabaseNotification $notification): string
    {
        return app(NotificationPresenter::class)->actionUrl($notification, Auth::user());
    }

    public function typeOptions(): array
    {
        return [
            'all' => 'Tout',
            'rendezvous' => 'Rendez-vous',
            'feedback' => 'Feedback',
            'finance' => 'Finance',
            'calendar' => 'Agenda',
            'admin' => 'Admin',
            'urgent' => 'Urgent',
            'system' => 'Système',
        ];
    }

    protected function findNotification(string $notificationId): ?DatabaseNotification
    {
        return Auth::user()
            ?->notifications()
            ->where('id', $notificationId)
            ->first();
    }

    public function render()
    {
        $presenter = app(NotificationPresenter::class);

        return view('livewire.notifications', [
            'notifications' => $this->notifications,
            'unreadCount' => $this->unreadCount,
            'presenter' => $presenter,
            'typeOptions' => $this->typeOptions(),
        ]);
    }
}
