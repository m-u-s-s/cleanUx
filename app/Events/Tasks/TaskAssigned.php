<?php

namespace App\Events\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 3 — Diffuse l'attribution d'une tâche en temps réel.
 *
 * Diffusé sur le channel privé de l'utilisateur assigné (pour notification toast)
 * ET sur le channel d'organisation pour rafraîchir le board partagé.
 */
class TaskAssigned implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Task $task,
        public User $assignedTo,
        public ?int $assignedBy = null,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('user.' . $this->assignedTo->id),
        ];

        // Si la tâche est rattachée à une organisation, on rafraîchit le board partagé
        if ($this->task->organization_account_id) {
            $channels[] = new PrivateChannel('presence-org.' . $this->task->organization_account_id);
        }

        // Si la tâche est rattachée à un canal de discussion, on rafraîchit le contexte
        if (property_exists($this->task, 'channel_id') && $this->task->channel_id) {
            $channels[] = new PrivateChannel('channel.' . $this->task->channel_id);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'task_id'      => $this->task->id,
            'title'        => $this->task->title ?? '',
            'priority'     => $this->task->priority ?? 'normal',
            'status'       => $this->task->status ?? 'pending',
            'due_at'       => $this->task->due_at?->toIso8601String(),
            'assigned_to'  => [
                'id'   => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ],
            'assigned_by'  => $this->assignedBy,
        ];
    }

    public function broadcastAs(): string
    {
        return 'TaskAssigned';
    }
}
