<?php

namespace App\Events\Tasks;

use App\Models\Task;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 3 — Diffuse un changement de statut d'une tâche.
 *
 * REVIEW FIX : property_exists() ne marche pas sur les attributs Eloquent.
 * Remplacé par ! empty() qui passe par __get correctement.
 */
class TaskStatusChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Task $task,
        public string $previousStatus,
        public string $newStatus,
        public ?int $changedBy = null,
    ) {}

    public function broadcastOn(): array
    {
        $channels = [];

        if (! empty($this->task->organization_account_id)) {
            $channels[] = new PrivateChannel('presence-org.' . $this->task->organization_account_id);
        }

        if (! empty($this->task->channel_id)) {
            $channels[] = new PrivateChannel('channel.' . $this->task->channel_id);
        }

        // Notifier l'assigné s'il y en a un
        if (! empty($this->task->assigned_to_user_id)) {
            $channels[] = new PrivateChannel('user.' . $this->task->assigned_to_user_id);
        }

        return $channels;
    }

    public function broadcastWith(): array
    {
        return [
            'task_id'         => $this->task->id,
            'previous_status' => $this->previousStatus,
            'new_status'      => $this->newStatus,
            'changed_by'      => $this->changedBy,
            'changed_at'      => now()->toIso8601String(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'TaskStatusChanged';
    }
}
