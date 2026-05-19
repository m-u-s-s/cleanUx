<?php

namespace App\Events\Realtime;

use App\Models\User;
use App\Realtime\Contracts\TracksBroadcastLedger;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Notification live cross-cutting pour un utilisateur — channel user.{id}.
 *
 * Cas d'usage : push d'un toast/banner en temps réel (booking confirmé,
 * paiement reçu, mission assignée) sans recharger la page.
 */
class UserLiveNotification implements ShouldBroadcastNow, TracksBroadcastLedger
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public User $user,
        public string $type,           // 'booking.confirmed', 'payment.received', etc.
        public ?string $title = null,
        public ?string $body = null,
        public array $data = [],
        public ?string $idempotencyKey = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->user->id)];
    }

    public function broadcastAs(): string
    {
        return $this->type;
    }

    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'data' => $this->data,
            'at' => now()->toIso8601String(),
        ];
    }

    public function broadcastCategory(): string
    {
        return \App\Models\BroadcastEvent::CATEGORY_NOTIFICATION;
    }

    public function broadcastIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function broadcastSourceType(): ?string
    {
        return User::class;
    }

    public function broadcastSourceId(): ?int
    {
        return $this->user->id;
    }

    public function broadcastLedgerPayload(): array
    {
        return [
            'user_id' => $this->user->id,
            ...$this->broadcastWith(),
        ];
    }
}
