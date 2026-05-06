<?php

namespace App\Events\Presence;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 3 — Diffuse un changement explicite de statut de présence.
 *
 * Note : la présence "automatique" (online/offline) est gérée par le presence
 * channel d'Echo (joining/leaving). Cet event sert pour les statuts manuels :
 *   - "Disponible" / "Occupé" / "En réunion" / "Pause"
 *
 * Diffusé sur le channel d'organisation pour que tous les coéquipiers le voient.
 */
class UserPresenceChanged implements ShouldBroadcast
{
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_BUSY      = 'busy';
    public const STATUS_AWAY      = 'away';
    public const STATUS_DND       = 'dnd';     // do not disturb
    public const STATUS_OFFLINE   = 'offline';

    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public User $user,
        public string $status,
        public ?string $customMessage = null,
        public ?int $organizationAccountId = null,
    ) {}

    public function broadcastOn(): array
    {
        $orgId = $this->organizationAccountId ?? $this->user->organization_account_id;

        $channels = [
            new PrivateChannel('user.' . $this->user->id),
        ];

        if ($orgId) {
            $channels[] = new PrivateChannel('presence-org.' . $orgId);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'user_id'        => $this->user->id,
            'name'           => $this->user->name,
            'status'         => $this->status,
            'custom_message' => $this->customMessage,
            'changed_at'     => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'UserPresenceChanged';
    }
}
