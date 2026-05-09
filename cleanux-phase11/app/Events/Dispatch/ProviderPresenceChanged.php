<?php

namespace App\Events\Dispatch;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 11 — Event broadcasté quand un prestataire change de presence.
 *
 * Permet au dashboard admin / dispatcheur de voir en temps réel qui est en
 * ligne. Émis sur un channel global "providers.presence".
 */
class ProviderPresenceChanged implements ShouldBroadcast
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public int $userId,
        public bool $isOnline,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('providers.presence'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'   => $this->userId,
            'is_online' => $this->isOnline,
            'at'        => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ProviderPresenceChanged';
    }
}
